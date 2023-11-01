<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\iq_group\Service\IqGroupUserManager;
use League\Csv\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an import form class to import group members.
 */
class ImportForm extends FormBase {

  /**
   * The entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager = NULL;

  /**
   * Configuration for the iq_group settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Gets the current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Gets the iq group user manager.
   *
   * @var \Drupal\iq_group\Service\IqGroupUserManager
   */
  protected $userManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * ImportForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Drupal\iq_group\Service\IqGroupUserManager $user_manager
   *   The iq group user manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    IqGroupUserManager $user_manager,
    FileSystemInterface $file_system,
    EntityRepositoryInterface $entity_repository,
    ModuleExtensionList $extension_list_module
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('iq_group.settings');
    $this->currentUser = $current_user;
    $this->userManager = $user_manager;
    $this->fileSystem = $file_system;
    $this->entityRepository = $entity_repository;
    $this->moduleExtensionList = $extension_list_module;
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
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('iq_group.user_manager'),
      $container->get('file_system'),
      $container->get('entity.repository'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iq_group_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Member Import'),
      '#description' => $this->t('Allowed file extension: .csv'),
      '#upload_location' => 'private://csv_import/import_data_form/' . $user->name->getString(),
      '#required' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
    ];
    // Choose to update user by an email field or the ID fields.
    $user_import_key_options = $this->userManager->userImportKeyOptions();
    $form['import_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Import key'),
      '#description' => $this->t('User will be updated by this field.'),
      '#options' => $user_import_key_options,
      '#default_value' => 'mail',
    ];
    $form['override_user'] = [
      '#type' => 'select',
      '#title' => $this->t('Override contact fields'),
      '#description' => $this->t('Override contact data fields (e.g. name, address, ...)'),
      '#options' => [
        'override_user' => $this->t('Override all fields'),
        'override_user_if_empty' => $this->t('Override if the existing fields are empty'),
        'override_user_hidden_fields' => $this->t('Override hidden fields'),
        'not_override_user' => $this->t('Do not override'),
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
        'not_override_tags' => $this->t('Do not override tags'),
      ],
      '#default_value' => 'override_tags',
      '#required' => TRUE,
    ];
    $form['override_preferences'] = [
      '#type' => 'select',
      '#title' => $this->t('Override user settings'),
      '#description' => $this->t('Choose what to do with the user preferences, branches'),
      '#options' => [
        'override_preferences' => $this->t('Override user settings'),
        'add_preferences' => $this->t('Append user settings'),
        'remove_preferences' => $this->t('Remove user settings'),
        'not_override_preferences' => $this->t('Do not override user settings'),
      ],
      '#default_value' => 'not_override_preferences',
      '#required' => TRUE,
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
      '#required' => TRUE,
    ];
    $form['delimiter'] = [
      '#type' => 'select',
      '#title' => $this->t('Delimiter'),
      '#description' => $this->t('Choose the delimiter for the csv file.'),
      '#options' => [
        'comma' => $this->t('Comma (,)'),
        'semi_colon' => $this->t('Semi colon (;)'),
        'tab' => $this->t('Tab (\t)'),
      ],
      '#default_value' => 'semi_colon',
      '#required' => TRUE,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $import = $form_state->getValue('import_file');
    /** @var \Drupal\file\FileInterface $import_file */
    $import_file = $this->entityTypeManager->getStorage('file')->load($import[0]);

    // Read CSV file.
    $import_file_uri = $import_file->getFileUri();
    $import_file_url = $this->fileSystem->realpath($import_file_uri);
    $reader = Reader::createFromPath($import_file_url, 'r');
    $delimiter = ',';
    if ($form_state->getValue('delimiter') == 'semi_colon') {
      $delimiter = ';';
    }
    elseif ($form_state->getValue('delimiter' == 'tab')) {
      $delimiter = '\t';
    }
    $reader->setDelimiter($delimiter);
    $reader->setHeaderOffset(0);

    // Get preference names.
    $preference_names = [];
    $result = $this->entityTypeManager
      ->getStorage('group')
      ->loadMultiple();

    /**
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $group) {
      $preference_names[$group->id()] = $group->label();
    }

    // Get Product IDs.
    $product_ids = [];
    $result = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'iq_group_products']);

    /**
     * @var  \Drupal\taxonomy\Entity\Term $product
     */
    foreach ($result as $product) {
      $product_ids[$product->id()] = $product->field_iq_group_product_id->value;
    }

    // Get branches IDs.
    $branch_ids = [];
    $result = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'branches']);

    /**
     * @var  \Drupal\taxonomy\Entity\Term $branch
     */
    foreach ($result as $branch) {
      if ($branch->hasTranslation('en')) {
        /** @var \Drupal\taxonomy\TermInterface $translated_term */
        $translated_term = $this->entityRepository->getTranslationFromContext($branch, 'en');
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
      'import_key' => $form_state->getValue('import_key'),
    ];

    // Existing tags.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
      ['vid' => 'tags']
    );
    $existing_terms = [];
    foreach ($terms as $term) {
      $existing_terms[$term->id()] = $term->label();
    }
    // Batch operations.
    $operations = [];

    try {
      for ($i = 0; $i < $reader->count(); $i += 10) {
        $operations[] = [
          'csv_import',
          [
            $import_file_url,
            $i,
            $preference_names,
            $this->currentUser->id(),
            $options,
            $existing_terms,
            $product_ids,
            $branch_ids,
          ],
        ];
      }
    }
    catch (\Exception $exception) {
      $this->messenger->addError($exception->getMessage());
      $this->messenger->addError('The csv file was not imported.');
      return;
    }

    $batch = [
      'title' => $this->t('Import...'),
      'operations' => $operations,
      'finished' => 'finished_import',
      'file' => $this->moduleExtensionList->getPath('iq_group') . '/import_batch.inc',
      'init_message' => $this->t('Starting import, this may take a while.'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('An error occurred during processing'),
    ];
    batch_set($batch);
  }

}
