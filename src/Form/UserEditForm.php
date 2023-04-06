<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\group\Entity\Group;
use Drupal\iq_group\Controller\UserController;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\UserInterface;

/**
 *
 */
class UserEditForm extends FormBase {

  /**
   * The Event dispatcher.
   *
   * @var Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher = NULL;

  /**
   * UserController constructor.
   *
   * @param Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher to dispatch events.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\iq_group\Controller\UserController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher')
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
    $currentPath = \Drupal::service('path.current')->getPath();
    ;
    if (!\Drupal::currentUser()->isAnonymous()) {
      $user = User::load(\Drupal::currentUser()->id());
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
      // Show him the link to the resource.
      $result = \Drupal::entityTypeManager()
        ->getStorage('group')
        ->loadMultiple();
      $options = [];
      $hidden_groups = UserController::getIqGroupSettings()['hidden_groups'];
      $hidden_groups = explode(',', $hidden_groups);
      /**
       * @var  int $key
       * @var  \Drupal\group\Entity\Group $group
       */
      foreach ($result as $key => $group) {
        if ($group->id() != \Drupal::config('iq_group.settings')->get('general_group_id') && !in_array($group->label(), $hidden_groups)) {
          $options[$group->id()] = $group->label();
        }
      }
      if ($user->hasField('field_iq_group_preferences')) {
        $selected_preferences = $user->get('field_iq_group_preferences')
          ->getValue();
        $default_value = [];
        foreach ($selected_preferences as $key => $value) {
          if ($value['target_id'] != \Drupal::config('iq_group.settings')->get('general_group_id')  && !in_array($group->label(), $hidden_groups)) {
            $default_value = array_merge($default_value, [$value['target_id']]);
          }
        }

        /** @var \Drupal\node\Entity\Node $node */
        $node = \Drupal::routeMatch()->getParameter('node');
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
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
        $term_options = [];
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        foreach ($terms as $term) {
          $term = Term::load($term->tid);
          if ($term->hasTranslation($language)) {
            $translated_term = \Drupal::service('entity.repository')
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
        foreach ($selected_branches as $key => $value) {
          $default_branches = array_merge($default_branches, [$value['target_id']]);
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
        $negotiator = \Drupal::languageManager()->getNegotiator();
        $user_language_added = $negotiator && $negotiator->isNegotiationMethodEnabled(LanguageNegotiationUser::METHOD_ID, LanguageInterface::TYPE_INTERFACE);
        $user_preferred_langcode = $user->getPreferredLangcode();
        $form['language'] = [
          '#type' => \Drupal::languageManager()->isMultilingual() ? 'details' : 'container',
          '#title' => $this->t('Language settings'),
          '#open' => TRUE,
          '#weight' => 40,
          // Display language selector when either creating a user on the admin
          // interface or editing a user account.
          // '#access' => !$register || $admin,.
        ];

        $form['language']['preferred_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Website language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE,
          '#default_value' => $user_preferred_langcode,
          '#description' => $user_language_added ? $this->t("The preferred language of this user account for e-mails and the presentation of the website.") : $this->t("This account's preferred language for emails."),
          // This is used to explain that user preferred language and entity
          // language are synchronized. It can be removed if a different behavior is
          // desired.
          '#weight' => 41,
        ];
      }

      $user_id = \Drupal::currentUser()->id();
      $group = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);
      if (in_array('subscription-subscriber', $groupRoles)) {
        $member_area_title = t('Create a login for your @project_name account', ['@project_name' => \Drupal::config('iq_group.settings')->get('project_name')]);
      }
      else {
        $member_area_title = t('Set your password');
      }
      $form['member_area'] = [
        '#type' => 'details',
        '#title' => $member_area_title,
        '#weight' => 50,
        '#open' => FALSE,
      ];
      $form['member_area']['password_text'] = [
        '#type' => 'markup',
        '#markup' => t('When you create a password, you are automatically creating a login.'),
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
          $resetURL = 'https://' . UserController::getDomain() . '/user/' . $user_id . '/edit';
          // @todo if there is a destination, attach it to the url
          $response = new RedirectResponse($resetURL, 302);
          $response->send();
          return;
        }
        else {
          unset($form['password']);
          unset($form['password_confirm']);
          unset($form['password_text']);
          $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
          $form['full_profile_edit'] = [
            '#type' => 'markup',
            '#markup' => '</br><a href="/' . $language . '/user/' . $user_id . '/edit">' . t('Edit profile') . '</a>',
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
    return $form;
  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('password') != $form_state->getValue('password_confirm')) {
      $form_state->setError($form['password'], t('The submitted passwords do not match.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = NULL;
    $user = \Drupal::entityTypeManager()->getStorage('user')->load(\Drupal::currentUser()->id());
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

      // Add the role in general (id=5) group.
      $group = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);
      if (!in_array('subscription-lead', $groupRoles)) {
        UserController::addGroupRoleToUser($group, $user, 'subscription-lead');
        $this->eventDispatcher->dispatch(IqGroupEvents::USER_PROFILE_EDIT, new IqGroupEvent($user));
      }
      // Add member to the other groups that the user has selected in the
      // preferences field.
      $groups = $form_state->getValue('preferences');
      foreach ($groups as $key => $otherGroup) {
        $otherGroup = Group::load($otherGroup);
        if ($otherGroup != NULL) {
          UserController::addGroupRoleToUser($otherGroup, $user, 'subscription-lead');
        }
      }
    }
    if ($form_state->getValue('branches') != NULL) {
      $user->set('field_iq_group_branches', $form_state->getValue('branches'));
    }
    if ($form_state->getValue('preferences') != NULL) {
      $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
    }

    \Drupal::messenger()->addMessage(t('Your profile has been saved.'));
    $user->save();
    $this->eventDispatcher->dispatch(IqGroupEvents::USER_PROFILE_EDIT, new IqGroupEvent($user));
    // Redirect after saving
    // It would be on the same page as the private resource, so no redirect.
  }

}
