<?php

/**
 * @file
 * Contains Drupal\iq_group\Form\ImportForm.
 */
namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use League\Csv\Reader;

class ImportForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'iq_group_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Member Import'),
      '#description' => $this->t('Allowed file extension: .csv'),
      '#upload_location' => 'private://csv_import/import_data_form/' . $user->name->getString(),
      '#required' => true,
      '#upload_validators' => array(
        'file_validate_extensions' => array('csv'),
      ),
    ];
    $form['override_user'] = [
      '#type' => 'select',
      '#title' => $this->t('User override'),
      '#description' => $this->t('Override existing users.'),
      '#options' => [
        'override_user' => $this->t('Override user if email exists'),
        'override_user_if_empty' => $this->t('Override user if the existing fields are empty')
      ],
      '#default_value' => 'override_user',
      '#required' => TRUE,
    ];
    $form['override_tags'] = [
      '#type' => 'select',
      '#title' => $this->t('Override tags'),
      '#description' => $this->t('Choose what to do with the tags'),
      '#options' => [
        'override_tags' => $this->t('Override tags'),
        'add_tags' => $this->t('Add tags'),
        'remove_tags' => $this->t('Remove tags')
      ],
      '#default_value' => 'override_tags',
      '#required'=> TRUE
    ];
    $form['override_preferences'] = [
      '#type' => 'select',
      '#title' => $this->t('Override preferences'),
      '#description' => $this->t('Choose what to do with the preferences'),
      '#options' => [
        'override_preferences' => $this->t('Override preferences'),
        'add_preferences' => $this->t('Add preferences'),
        'remove_preferences' => $this->t('Remove preferences')
      ],
      '#default_value' => 'override_preferences',
      '#required'=> TRUE
    ];
    $form['delimiter'] = [
      '#type' => 'select',
      '#title' => $this->t('Delimiter'),
      '#description' => $this->t('Choose the delimiter for the csv file.'),
      '#options' => [
        'comma' => $this->t('Comma (,)'),
        'semi_colon' => $this->t('Semi colon (;)'),
        'tab' => $this->t('Tab (\t)')
      ],
      '#default_value' => 'comma',
      '#required'=> TRUE
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Import data',
      '#button_type' => 'primary',
    ];
    return $form;
  }



  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $import = $form_state->getValue('import_file');
    $import_file = \Drupal\file\Entity\File::load($import[0]);

    // read CSV file.
    $import_file_uri = $import_file->getFileUri();
    $import_file_url = \Drupal::service('file_system')->realpath($import_file_uri);
    $reader = Reader::createFromPath($import_file_url, 'r');
    $delimiter = ',';
    if ($form_state->getValue('delimiter') == 'semi_colon') {
      $delimiter = ';';
    }
    else if ($form_state->getValue('delimiter' == 'tab')) {
      $delimiter = '\t';
    }
    $reader->setDelimiter($delimiter);
    $reader->setHeaderOffset(0);

    // Get preference names.
    $preference_names = [];
    $result = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadMultiple();

    /**
     * @var  int $key
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $key => $group) {
        $preference_names[$group->id()] = $group->label();
    }

    // Get options for the tags and user override.
    $options = [
      'tag_option' => $form_state->getValue('override_tags'),
      'user_option' => $form_state->getValue('override_user'),
      'preference_option' => $form_state->getValue('override_preferences'),
      'delimiter' => $delimiter
    ];


    // Existing tags.
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(
      ['vid' => 'tags']
    );
    $existing_terms = [];
    foreach ($terms as $term) {
      $existing_terms[$term->id()] = $term->label();
    }
    // Batch operations.
    $operations = [];

    for($i = 0; $i < $reader->count(); $i+=20) {
      $operations[] = ['csv_import', [$import_file_url, $i, $preference_names, \Drupal::currentUser()->id(), $options, $existing_terms]];
    }

    $batch = array(
      'title' => t('Import...'),
      'operations' => $operations,
      'finished' => 'finished_import',
      'file' => drupal_get_path('module', 'iq_group') . '/import_batch.inc',
      'init_message' => t('Starting import, this may take a while.'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('An error occurred during processing'),
    );
    batch_set($batch);
  }
}