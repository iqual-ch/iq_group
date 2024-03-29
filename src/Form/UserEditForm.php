<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\iq_group\Service\IqGroupUserManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Custom user edit form for the iq_group module.
 */
class UserEditForm extends FormBase {

  /**
   * Configuration for the iq_group settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * UserEditForm constructor.
   *
   * @param Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher to dispatch events.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current active user.
   * @param \Drupal\iq_group\Service\IqGroupUserManager $userManager
   *   The iq group user manager.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $config_factory,
    protected LanguageManagerInterface $languageManager,
    protected AccountProxyInterface $currentUser,
    protected IqGroupUserManager $userManager,
    protected CurrentPathStack $currentPath,
    protected EntityRepositoryInterface $entityRepository
  ) {
    $this->config = $config_factory->get('iq_group.settings');
  }

  /**
   * Creates a UserEditForm instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\iq_group\Form\UserEditForm
   *   An instance of UserEditForm.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('iq_group.user_manager'),
      $container->get('path.current'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_group_user_edit_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $currentPath = $this->currentPath->getPath();
    if (!$this->currentUser->isAnonymous()) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      $default_name = $user->getAccountName();
      $form['name'] = [
        '#type' => 'hidden',
        '#title' => $this->t('Username'),
        '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
        '#description' => $this->t("Some special characters are allowed, such as space, dot (.), hyphen (-), apostrophe ('), underscore(_) and the @ character."),
        '#required' => FALSE,
        '#default_value' => $default_name,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
        '#weight' => 10,
      ];
      // Show user the link to the resource.
      $result = $this->entityTypeManager
        ->getStorage('group')
        ->loadMultiple();
      $options = [];
      $hidden_groups = $this->userManager->getIqGroupSettings()['hidden_groups'];
      $hidden_groups = explode(',', (string) $hidden_groups);
      /**
       * @var  int $key
       * @var  \Drupal\group\Entity\Group $group
       */
      foreach ($result as $group) {
        if ($group->id() != $this->config->get('general_group_id') && !in_array($group->label(), $hidden_groups)) {
          $options[$group->id()] = $group->label();
        }
      }
      if ($user->hasField('field_iq_group_preferences')) {
        $selected_preferences = $user->get('field_iq_group_preferences')
          ->getValue();
        $default_value = [];
        foreach ($selected_preferences as $value) {
          if ($value['target_id'] != $this->config->get('general_group_id')  && !in_array($group->label(), $hidden_groups)) {
            $default_value = [...$default_value, $value['target_id']];
          }
        }

        if ($currentPath == '/user/edit') {
          $form['preferences'] = [
            '#type' => 'checkboxes',
            '#options' => $options,
            '#multiple' => TRUE,
            '#default_value' => $default_value,
            '#title' => $this->t('Preferences'),
            '#weight' => 20,
          ];
        }
      }

      if ($user->hasField('field_iq_group_branches')) {
        $vid = 'branches';
        /** @var \Drupal\taxonomy\StorageInterface $term_storage */
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $terms = $term_storage->loadTree($vid);
        $term_options = [];
        $language = $this->languageManager->getCurrentLanguage()->getId();
        foreach ($terms as $term) {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term->tid);
          if ($term->hasTranslation($language)) {
            /** @var \Drupal\taxonomy\TermInterface $translated_term */
            $translated_term = $this->entityRepository
              ->getTranslationFromContext($term, $language);
            $term_options[$translated_term->id()] = $translated_term->getName();
          }
          else {
            $term_options[$term->id()] = $term->getName();
          }
        }
        $selected_branches = $user->get('field_iq_group_branches')
          ->getValue();
        $default_branches = [];
        foreach ($selected_branches as $value) {
          $default_branches = [...$default_branches, $value['target_id']];
        }
        if ($currentPath == '/user/edit') {
          $form['branches'] = [
            '#type' => 'checkboxes',
            '#options' => $term_options,
            '#default_value' => $default_branches,
            '#multiple' => TRUE,
            '#title' => $this->t('Branches'),
            '#weight' => 30,
          ];
        }
      }

      if ($currentPath == '/user/edit') {
        $user_language_added = FALSE;
        if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
          $negotiator = $this->languageManager->getNegotiator();
          $user_language_added =
          $negotiator &&
          $negotiator->isNegotiationMethodEnabled(
            LanguageNegotiationUser::METHOD_ID,
            LanguageInterface::TYPE_INTERFACE
          );
        }
        $user_preferred_langcode = $user->getPreferredLangcode();
        $form['language'] = [
          '#type' => $this->languageManager->isMultilingual() ? 'details' : 'container',
          '#title' => $this->t('Language settings'),
          '#open' => TRUE,
          '#weight' => 40,
          /* Display language selector when either creating a user on the admin
           * interface or editing a user account.
           * '#access' => !$register || $admin,.
           */
        ];

        $form['language']['preferred_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Website language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE,
          '#default_value' => $user_preferred_langcode,
          '#description' => $user_language_added ?
          $this->t("The preferred language of this user account for e-mails and the presentation of the website.") :
          $this->t("This account's preferred language for emails."),
          /*
           * This is used to explain that user preferred language and entity
           * language are synchronized. It can be removed
           * if a different behavior is desired.
           */
          '#weight' => 41,
        ];
      }

      $group = $this->userManager->getGeneralGroup();
      if ($group) {
        $user_id = $this->currentUser->id();
        /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $group_role_storage */
        $group_role_storage = $this->entityTypeManager->getStorage('group_role');
        $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
        $groupRoles = array_keys($groupRoles);
        if (in_array('subscription-subscriber', $groupRoles)) {
          $member_area_title = $this->t('Create a login for your @project_name account', ['@project_name' => $this->config->get('project_name')]);
        }
        else {
          $member_area_title = $this->t('Set your password');
        }
        $form['member_area'] = [
          '#type' => 'details',
          '#title' => $member_area_title,
          '#weight' => 50,
          '#open' => FALSE,
        ];
        $form['member_area']['password_text'] = [
          '#type' => 'markup',
          '#markup' => $this->t('When you create a password, you are automatically creating a login.'),
          '#weight' => 60,
        ];
        $form['member_area']['password'] = [
          '#type' => 'password',
          '#title' => $this->t('Password'),
          '#weight' => 61,
        ];
        $form['member_area']['password_confirm'] = [
          '#type' => 'password',
          '#title' => $this->t('Confirm password'),
          '#weight' => 62,
        ];

        // If user is a lead, show link or edit profile directly.
        if (in_array('subscription-lead', $groupRoles)) {
          if ($currentPath == '/user/edit') {
            $resetURL = 'https://' . $this->userManager->getDomain() . '/user/' . $user_id . '/edit';
            // @todo if there is a destination, attach it to the url
            $response = new RedirectResponse($resetURL, 302);
            $response->send();
            return;
          }
          else {
            unset($form['password']);
            unset($form['password_confirm']);
            unset($form['password_text']);
            $language = $this->languageManager->getCurrentLanguage()->getId();
            $form['full_profile_edit'] = [
              '#type' => 'link',
              '#title' => $this->t('Edit profile'),
              '#url' => Url::fromRoute('entity.user.edit_form', ['user' => $user_id]),
              '#weight' => 70,
            ];
          }
        }

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save'),
          '#button_type' => 'primary',
          '#weight' => 80,
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('password') != $form_state->getValue('password_confirm')) {
      $form_state->setError($form['password'], $this->t('The submitted passwords do not match.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = NULL;
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($form_state->getValue('name') != NULL) {
      $name = $form_state->getValue('name');
    }
    $user->set('name', $name);
    if ($form_state->getValue('preferred_langcode') != NULL) {
      $user->set('preferred_langcode', $form_state->getValue('preferred_langcode'));
      $user->set('langcode', $form_state->getValue('preferred_langcode'));
    }
    if ($form_state->getValue('password') != NULL) {
      $user->setPassword($form_state->getValue('password'));

      // Add the role in general group.
      $group = $this->userManager->getGeneralGroup();
      if ($group) {
        /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $group_role_storage */
        $group_role_storage = $this->entityTypeManager->getStorage('group_role');
        $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
        $groupRoles = array_keys($groupRoles);
        if (!in_array('subscription-lead', $groupRoles)) {
          $this->userManager->addGroupRoleToUser($group, $user, 'subscription-lead');
          $this->eventDispatcher->dispatch(IqGroupEvents::USER_PROFILE_EDIT, new IqGroupEvent($user));
        }
      }

      // Add member to the other groups that the user has selected in the
      // preferences field.
      $groups = $form_state->getValue('preferences');
      foreach ($groups as $otherGroup) {
        $otherGroup = $this->entityTypeManager->getStorage('group')->load($otherGroup);
        if ($otherGroup != NULL) {
          $this->userManager->addGroupRoleToUser($otherGroup, $user, 'subscription-lead');
        }
      }
    }
    if ($form_state->getValue('branches') != NULL) {
      $user->set('field_iq_group_branches', $form_state->getValue('branches'));
    }
    if ($form_state->getValue('preferences') != NULL) {
      $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
    }

    $this->messenger()->addMessage($this->t('Your profile has been saved.'));
    $user->save();
    $this->eventDispatcher->dispatch(IqGroupEvents::USER_PROFILE_EDIT, new IqGroupEvent($user));
    // Redirect after saving
    // It would be on the same page as the private resource, so no redirect.
  }

}
