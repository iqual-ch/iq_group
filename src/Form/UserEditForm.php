<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\iq_group\Controller\UserController;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserEditForm extends FormBase
{

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
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $currentPath =  \Drupal::service('path.current')->getPath();;
    if (!\Drupal::currentUser()->isAnonymous()) {
      $user = User::load(\Drupal::currentUser()->id());
      $default_name = $user->getAccountName();
      $form['name'] = [
        '#type' => 'hidden',
        '#title' => $this->t('Username'),
        '#maxlength' => USERNAME_MAX_LENGTH,
        '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
        '#required' => FALSE,
        '#default_value' => $default_name,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
      ];
      // show him the link to the resource
      $result = \Drupal::entityTypeManager()
        ->getStorage('group')
        ->loadMultiple();
      $options = [];
      /**
       * @var  int $key
       * @var  \Drupal\group\Entity\Group $group
       */
      foreach ($result as $key => $group) {
        if ($group->id()!=\Drupal::config('iq_group.settings')->get('general_group_id'))
          $options[$group->id()] = $group->label();
      }
      $selected_preferences = $user->get('field_iq_group_preferences')
        ->getValue();
      $default_value = [];
      foreach ($selected_preferences as $key => $value) {
        if ($value['target_id'] != \Drupal::config('iq_group.settings')->get('general_group_id'))
          $default_value = array_merge($default_value, [$value['target_id']]);
      }

      /** @var Node $node */
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($currentPath == '/user/edit' || (!empty($node) && $node->bundle() == 'iq_group_whitepaper')) {
        $form['preferences'] = [
          '#type' => 'checkboxes',
          '#options' => $options,
          '#multiple' => TRUE,
          '#default_value' => $default_value,
          '#title' => $this->t('Preferences')
        ];
      }
      $vid = 'branches';
      $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
      $term_options = [];
      foreach ($terms as $term) {
        $term_options[$term->tid] = $term->name;
      }
      $selected_branches = $user->get('field_iq_group_branches')
        ->getValue();
      $default_branches = [];
      foreach ($selected_branches as $key => $value) {
        if ($value['target_id'] != \Drupal::config('iq_group.settings')->get('general_group_id'))
          $default_branches = array_merge($default_branches, [$value['target_id']]);
      }

      if ($currentPath == '/user/edit' || (!empty($node) && $node->bundle() == 'iq_group_whitepaper')) {
        $form['branches'] = [
          '#type' => 'checkboxes',
          '#options' => $term_options,
          '#default_value' => $default_branches,
          '#multiple' => TRUE,
          '#title' => $this->t('Branches')
        ];
        $negotiator = \Drupal::languageManager()->getNegotiator();
        $user_language_added = $negotiator && $negotiator->isNegotiationMethodEnabled(LanguageNegotiationUser::METHOD_ID, LanguageInterface::TYPE_INTERFACE);
        $user_preferred_langcode = $user->getPreferredLangcode();
        $form['language'] = [
          '#type' => \Drupal::languageManager()->isMultilingual() ? 'details' : 'container',
          '#title' => $this->t('Language settings'),
          '#open' => TRUE,
          // Display language selector when either creating a user on the admin
          // interface or editing a user account.
          //'#access' => !$register || $admin,
        ];

        $form['language']['preferred_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Site language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE,
          '#default_value' => $user_preferred_langcode,
          '#description' => $user_language_added ? $this->t("This account's preferred language for emails and site presentation.") : $this->t("This account's preferred language for emails."),
          // This is used to explain that user preferred language and entity
          // language are synchronized. It can be removed if a different behavior is
          // desired.
        ];

      }
      $form['password_text'] = [
        '#type' => 'markup',
        '#markup' => 'Wenn Sie ein Passwort setzen, erstellen Sie automatisch ein Login. Sie können anschliessend Ihre Newsletter Präferenzen direkt im Benutzerkonto ändern.'
      ];
      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password')
      ];
      $form['password_confirm'] = [
        '#type' => 'password',
        '#title' => $this->t('Confirm password')
      ];
      $user_id = \Drupal::currentUser()->id();
      $group = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);
      // If user is a lead, show link or edit profile directly.
      if (in_array('subscription-lead', $groupRoles)) {
        if ($currentPath == '/user/edit') {
          $resetURL = 'https://' . RegisterForm::getDomain() . '/user/' . $user_id .'/edit';
          // @todo if there is a destination, attach it to the url
          $response = new RedirectResponse($resetURL, 302);
          $response->send();
          return;
        }
        else {
          unset($form['password']);
          unset($form['password_confirm']);
          unset($form['password_text']);
          $form['full_profile_edit'] = [
            '#type' => 'markup',
            '#markup' => '</br><a href="/user/' . $user_id . '/edit">' . t('Edit profile') . '</a>'
          ];
          if (!empty($node) && $node->bundle() == 'iq_group_whitepaper') {
            $form['actions']['#type'] = 'actions';
            $form['actions']['submit'] = [
              '#type' => 'submit',
              '#value' => $this->t('Save'),
              '#button_type' => 'primary',
            ];
          }
          return $form;
        }
      }

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('password') != $form_state->getValue('password_confirm')) {
      $form_state->setError($form['password'], t('The specified passwords do not match.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser()->getAccount();
    if ($form_state->getValue('name') != NULL) {
      $name = $form_state->getValue('name');
    }
    $user->set('name', $name);
    if ($form_state->getValue('language.preferred_langcode') != NULL) {
      $user->set('preferred_langcode', $form_state->getValue('language.preferred_langcode'));
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
        if ($otherGroup != NULL)
          UserController::addGroupRoleToUser($otherGroup, $user, 'subscription-lead');
      }
    }
    $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
    $user->set('field_iq_group_branches', $form_state->getValue('branches'));

    $user->save();
    $this->eventDispatcher->dispatch(IqGroupEvents::USER_PROFILE_EDIT, new IqGroupEvent($user));
    // Redirect after saving
    // It would be on the same page as the private resource, so no redirect.
  }
  public static function getDomain() {
    if (!empty($_SERVER["HTTP_HOST"]) || getenv("VIRTUAL_HOSTS")) {
      $virtual_host = "";
      if (getenv("VIRTUAL_HOSTS")) {
        $virtual_hosts = explode(",", getenv("VIRTUAL_HOSTS"));

        if (count($virtual_hosts) > 1) {
          $virtual_host = $virtual_hosts[1];
        } else {
          $virtual_host = $virtual_hosts[0];
        }
      }
      $domain = empty($virtual_host) ? $_SERVER["HTTP_HOST"] : $virtual_host;
    }
    return $domain;
  }
}