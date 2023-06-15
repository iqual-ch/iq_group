<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for the iq_group module settings.
 *
 * @package Drupal\iq_group\Form
 */
class IqGroupSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iq_group_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $iqGroupSettings = \Drupal::service('iq_group.user_manager')->getIqGroupSettings();

    $form['default_redirection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default redirection'),
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => $this->t('Add a valid url for the default page'),
      '#default_value' => $iqGroupSettings['default_redirection'],
    ];
    $form['redirection_after_register'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirection after Registration'),
      '#description' => $this->t('Add a valid url to redirect to after registration'),
      '#default_value' => $iqGroupSettings['redirection_after_register'],
    ];
    $form['redirection_after_account_delete'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirection after the account is deleted'),
      '#description' => $this->t('Add a valid url to redirect to after the account is deleted.'),
      '#default_value' => $iqGroupSettings['redirection_after_account_delete'],
    ];
    $form['redirection_after_signup'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirection after Signup'),
      '#description' => $this->t('Add a valid url to redirect to after signup'),
      '#default_value' => $iqGroupSettings['redirection_after_signup'],
    ];

    $form['import_users'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IQ Group Import Settings'),
      '#description' => $this->t('IQ Group Import Settings'),
      '#attributes' => [
        'style' => 'width: 50em;',
      ],
    ];
    $form['import_users']['hidden_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Hidden fields'),
      '#description' => $this->t('Enter the hidden fields for the import separated by a comma (,).'),
      '#default_value' => $iqGroupSettings['hidden_fields'],
      '#attributes' => [
        'style' => 'width: 50em;',
      ],
    ];
    $form['general_group_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('General group ID'),
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => $this->t('Enter the general group ID'),
      '#default_value' => $iqGroupSettings['general_group_id'],
    ];
    $form['hidden_groups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Hidden Preferences'),
      '#description' => $this->t('Enter the hidden preferences for forms separated by a comma (,).'),
      '#default_value' => $iqGroupSettings['hidden_groups'],
      '#attributes' => [
        'style' => 'width: 50em;',
      ],
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of the sender'),
      '#default_value' => $iqGroupSettings['name'],
    ];
    $form['from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email address of the sender'),
      '#description' => $this->t('This will be displayed as the sender on the emails.'),
      '#default_value' => $iqGroupSettings['from'],
    ];
    $form['reply_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply-to address'),
      '#description' => $this->t('This will be the reply-to email address.'),
      '#default_value' => $iqGroupSettings['reply_to'],
    ];

    $form['login_intro'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login introduction text'),
      '#description' => $this->t('This text will be displayed on the login page.'),
      '#default_value' => $iqGroupSettings['login_intro'],
    ];
    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name'),
      '#description' => $this->t('This name will be used in emails.'),
      '#default_value' => $iqGroupSettings['project_name'],
    ];
    $form['project_address'] = [
      '#type' => 'textarea',
      '#width' => 16,
      '#title' => $this->t('Project Address'),
      '#description' => $this->t('This address will be displayed in the footer of the email.'),
      '#default_value' => $iqGroupSettings['project_address'],
      '#attributes' => [
        'style' => 'width: 50em;',
      ],
    ];
    $form['terms_and_conditions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terms and conditions'),
      '#description' => $this->t('This URL will be linked with the terms and conditions in the forms.'),
      '#default_value' => $iqGroupSettings['terms_and_conditions'],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->config('iq_group.settings')->get('hidden_groups') != $form_state->getValue('hidden_groups')) {
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['iq_group:signup_block']);
    }
    $this->config('iq_group.settings')
      ->set('default_redirection', $form_state->getValue('default_redirection'))
      ->set('general_group_id', $form_state->getValue('general_group_id'))
      ->set('hidden_groups', $form_state->getValue('hidden_groups'))
      ->set('name', $form_state->getValue('name'))
      ->set('from', $form_state->getValue('from'))
      ->set('reply_to', $form_state->getValue('reply_to'))
      ->set('login_intro', $form_state->getValue('login_intro'))
      ->set('project_name', $form_state->getValue('project_name'))
      ->set('terms_and_conditions', $form_state->getValue('terms_and_conditions'))
      ->set('redirection_after_register', $form_state->getValue('redirection_after_register'))
      ->set('redirection_after_account_delete', $form_state->getValue('redirection_after_account_delete'))
      ->set('redirection_after_signup', $form_state->getValue('redirection_after_signup'))
      ->set('project_address', $form_state->getValue('project_address'))
      ->set('hidden_fields', $form_state->getValue('hidden_fields'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['iq_group.settings'];
  }

}
