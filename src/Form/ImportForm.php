<?php

/**
 * @file
 * Contains Drupal\iq_group\Form\ImportForm.
 */
namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iq_group\Controller\UserController;
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
    // Choose to update user by an email field or the ID fields.
    $user_import_key_options = UserController::userImportKeyOptions();
    $form['import_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Import key'),
      '#description' => $this->t('User will be updated by this field.'),
      '#options' => $user_import_key_options,
      '#default_value' => 'mail'
    ];
    $form['override_user'] = [
      '#type' => 'select',
      '#title' => $this->t('Override contact fields'),
      '#description' => $this->t('Override contact data fields (e.g. name, address, ...)'),
      '#options' => [
        'override_user' => $this->t('Override all fields'),
        'override_user_if_empty' => $this->t('Override user if the existing fields are empty'),
        'override_user_hidden_fields' => $this->t('Override hidden fields'),
        'not_override_user' => $this->t('Do not override')
      ],
      '#default_value' => 'override_user_hidden_fields',
      '#required' => TRUE,
    ];
    $form['override_tags'] = [
      '#type' => 'select',
      '#title' => $this->t('Override tags'),
      '#description' => $this->t('Choose what to do with the tags'),
      '#options' => [
        'override_tags' => $this->t('Override tags'),
        'add_tags' => $this->t('Append tags'),
        'remove_tags' => $this->t('Remove tags'),
        'not_override_tags' => $this->t('Do not override tags')
      ],
      '#default_value' => 'override_tags',
      '#required'=> TRUE
    ];
    $form['override_preferences'] = [
      '#type' => 'select',
      '#title' => $this->t('Override user settings'),
      '#description' => $this->t('Choose what to do with the user preferences, branches'),
      '#options' => [
        'override_preferences' => $this->t('Override user settings'),
        'add_preferences' => $this->t('Append user settings'),
        'remove_preferences' => $this->t('Remove user settings'),
        'not_override_preferences' => $this->t('Do not override user settings')
      ],
      '#default_value' => 'not_override_preferences',
      '#required'=> TRUE
    ];
    $form['override_product'] = [
      '#type' => 'select',
      '#title' => $this->t('Override product settings'),
      '#description' => $this->t('Choose what to do with the user product field'),
      '#options' => [
        'override_product' => $this->t('Override product field'),
        'not_override_product' => $this->t('Do not override product field'),
      ],
      '#default_value' => 'not_override_product',
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
      '#default_value' => 'semi_colon',
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

    // Get Product IDs.
    $product_ids = [];
    $result = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'iq_group_products']);

    /**
     * @var  int $key
     * @var  \Drupal\taxonomy\Entity\Term $product
     */
    foreach ($result as $key => $product) {
      $product_ids[$product->id()] = $product->field_iq_group_product_id->value;
    }

    // Get branches IDs.
    $branch_ids = [];
    $result = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'branches']);

    /**
     * @var  int $key
     * @var  \Drupal\taxonomy\Entity\Term $branch
     */
    foreach ($result as $key => $branch) {
      if($branch->hasTranslation('en')){
        $translated_term = \Drupal::service('entity.repository')->getTranslationFromContext($branch, 'en');
        $branch_ids[$branch->id()] = $translated_term->getName();
      }
      else {
        $branch_ids[$branch->id()] = $branch->getName();
      }
    }

    // Get options for the tags and user override.
    $options = [
      'tag_option' => $form_state->getValue('override_tags'),
      'user_option' => $form_state->getValue('override_user'),
      'preference_option' => $form_state->getValue('override_preferences'),
      'product_option' => $form_state->getValue('override_product'),
      'delimiter' => $delimiter,
      'import_key' => $form_state->getValue('import_key')
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

    for($i = 0; $i < $reader->count(); $i+=10) {
      $operations[] = ['csv_import', [$import_file_url, $i, $preference_names, \Drupal::currentUser()->id(), $options, $existing_terms, $product_ids, $branch_ids]];
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
