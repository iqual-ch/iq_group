<?php

namespace Drupal\iq_group_sqs_mautic\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Class IqGroupSettingsForm.
 *
 * @package Drupal\iq_group_sqs_mautic\Form
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
    $config = $this->config('iq_group_sqs_mautic.settings');
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
      '#default_value' => '5'
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
    $this->config('iq_group_sqs_mautic.settings')
      ->set('default_redirection', $form_state->getValue('default_redirection'))
      ->set('general_group_id', $form_state->getValue('general_group_id'))
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
    return ['iq_group_sqs_mautic.settings'];
  }

}
