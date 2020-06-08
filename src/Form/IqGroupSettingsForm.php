<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Class IqGroupSettingsForm.
 *
 * @package Drupal\iq_group\Form
 */
class IqGroupSettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'iq_group_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('iq_group.settings');
    $default_redirection = $config->get('default_redirection');
    $general_group_id = $config->get('general_group_id');

    $form['default_redirection'] = [
      '#type' => 'textfield',
      '#title' => 'Default redirection',
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => $this->t('Add a valid url for the default page'),
      '#default_value' => isset($default_redirection) ? $default_redirection : '',
    ];
    $form['general_group_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('General group ID'),
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => $this->t('Enter the general group ID'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('general_group_id')
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of the sender'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('name')
    ];
    $form['from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email address of the sender'),
      '#description' => $this->t('This will be displayed as the sender on the emails.'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('from')
    ];
    $form['reply_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply-to address'),
      '#description' => $this->t('This will be the reply-to email address.'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('reply_to')
    ];

    $form['login_intro'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login introduction text'),
      '#description' => $this->t('This text will be displayed on the login page.'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('login_intro')
    ];
    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name'),
      '#description' => $this->t('This name will be used in emails.'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('project_name')
    ];
    $form['terms_and_conditions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terms and conditions'),
      '#description' => $this->t('This URL will be linked with the terms and conditions in the forms.'),
      '#default_value' => \Drupal::config('iq_group.settings')->get('terms_and_conditions')
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
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('iq_group.settings')
      ->set('default_redirection', $form_state->getValue('default_redirection'))
      ->set('general_group_id', $form_state->getValue('general_group_id'))
      ->set('name', $form_state->getValue('name'))
      ->set('from', $form_state->getValue('from'))
      ->set('reply_to', $form_state->getValue('reply_to'))
      ->set('login_intro', $form_state->getValue('login_intro'))
      ->set('project_name', $form_state->getValue('project_name'))
      ->set('terms_and_conditions', $form_state->getValue('terms_and_conditions'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames()
  {
    return ['iq_group.settings'];
  }

}
