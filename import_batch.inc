<?php

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\group\Entity\Group;
use Drupal\iq_group\Controller\UserController;
use Drupal\user\Entity\User;
use League\Csv\Reader;

function csv_import($import_file_url, $i, $preference_names, $current_user, $options, $existing_terms, $product_ids, $branch_ids, &$context)
{
  $reader = Reader::createFromPath($import_file_url, 'r');
  $reader->setDelimiter($options['delimiter']);
  $reader->setHeaderOffset(0);
  // From import form, when chunking the data.
  // Get user records.
  $stmt = (new \League\Csv\Statement())
    ->offset($i)
    ->limit(10);
  $records = $stmt->process($reader);
  $j = 1;

  $iqGroupSettings = UserController::getIqGroupSettings();
  $hidden_fields = $iqGroupSettings['hidden_fields'];
  $hidden_fields = array_map('trim', explode(',', $hidden_fields));

  // Field mapping.
  $field_mappings = [
    'salutation' => 'field_iq_group_salutation',
    'given_name' => 'field_iq_user_base_address',
    'family_name' => 'field_iq_user_base_address',
    'preferences' => 'field_iq_group_preferences',
    'products' => 'field_iq_group_products',
    'branches' => 'field_iq_group_branches',
    'tags' => 'field_iq_group_tags',
    'position' => 'field_iq_user_base_position',
    'organization' => 'field_iq_user_base_address',
    'postal_code' => 'field_iq_user_base_address',
    'address_line1' => 'field_iq_user_base_address',
    'locality' => 'field_iq_user_base_address',
    'country_code' => 'field_iq_user_base_address',
    'mail' => 'mail',
    'langcode' => 'langcode',
    'preferred_langcode' => 'preferred_langcode',
    'drupal_id' => 'uid'
  ];
  if (empty($context['results'])) {
    $context['sandbox'] = ['set' => 'now it is set'];
    $context['sandbox']['progress'] = 0;
    $context['results'] = [];
    $context['results']['log_error'] = '';
    $context['results']['log_warning'] = '';
    $context['results']['cnt_import'] = 0;
    $context['results']['cnt_update'] = 0;
    $context['results']['cnt_error'] = 0;
    $context['results']['cnt_warning'] = 0;
    $context['results']['processed_users'] = [];
    $context['results']['start'] = microtime(TRUE);
  }

  // Go through the 10 records.
  foreach ($records as $offset => $user_data) {
    // Get the field keys only from the 1st user (they are the same for every user).
    if ($j == 1) {
      $field_keys = [];
      $unused_keys = [];
      foreach ($user_data as $key => $value) {
        if (strpos($key, 'field_') > -1) {
          $field_keys[$key]  = $key;
        }
        else if (!empty($field_mappings[$key])) {
          $field_keys[$key] = $field_mappings[$key];
        }
        else {
          $unused_keys[] = $key;
        }
      }
      $j++;
    }

    if (validateUser($user_data, $field_keys, $offset, $context)) {

      \Drupal::moduleHandler()
        ->invokeAll('iq_group_before_import', [&$user_data]);
      // import user.
      // Check if user already exists by the import key.
      $import_key = $options['import_key'];
      if (!empty($import_key) && !empty($user_data[$import_key])) {
        $checkingUser = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties([
            $import_key => $user_data[$import_key]
          ]);
      }
      // Flag to indicate whether the user exists already.
      $found_user = FALSE;

      $store = \Drupal::service('tempstore.shared')->get('iq_group.user_status');
      $found_import_key = $options['import_key'];
      // Get the existing user or create a new user.
      if (!empty($checkingUser)) {
        $found_user = TRUE;
        $user = reset($checkingUser);
        $context['results']['cnt_update']++;
      }
      // Make sure that there is no other user associated with the import keys.
      $user_import_key_options = UserController::userImportKeyOptions();
      unset($user_import_key_options[$options['import_key']]);
      $conflicting_user = FALSE;
      foreach ($user_import_key_options as $import_key_option => $import_value_option) {
        if (!empty($user_data[$import_key_option])) {
          $checkingUser = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->loadByProperties([
              $import_key_option => $user_data[$import_key_option]
            ]);
          // If it found a conflicting user.
          if (!empty($checkingUser)) {
            $check_user = reset($checkingUser);
            // If the conflicting user is not the same as the found user before
            // or there is an existing conflicting user when creating a new user.
            if (($found_user && $check_user->id() != $user->id()) || !$found_user) {
              $conflicting_user = TRUE;
              $found_import_key = $import_key_option;
              break;
            }
          }
        }
      }

      // Log if a conflicting user is found, and continue if it's the email
      if ($conflicting_user) {
        // @TODO Decide what to do if a user is found with an import key
        // @TODO that was not specified in the import settings.
        if (empty($context['results']['log_error'])) {
          $context['results']['log_error'] = [];
        }
        if (empty($context['results']['log_warning'])) {
          $context['results']['log_warning'] = [];
        }
        if ($found_import_key == 'mail') {
          if (empty($context['results']['log_error']['import_key_error' . $found_import_key]) && !isset($context['results']['log_error']['import_key_error' . $found_import_key]['message'])) {
            $context['results']['log_error']['import_key_error' . $found_import_key]['message'] = t('Multiple users found with the same ' . $found_import_key .  ' at rows: ');
          }
          $context['results']['log_error']['import_key_error' . $found_import_key]['rows'][] = (int)$offset+1;
          if (empty($context['results']['log_error']['conflicting_import_keys']) && !isset($context['results']['log_error']['conflicting_import_keys']['message'])) {
            $context['results']['log_error']['conflicting_import_keys']['message'] = t('Skipped import for users at rows:');
          }
          $context['results']['log_error']['conflicting_import_keys']['rows'][] = (int)$offset+1;
          $context['results']['cnt_error']++;
          $context['results']['cnt_update']--;
          continue;
        } else {
          if (empty($context['results']['log_warning']['import_key_error' . $found_import_key]) && !isset($context['results']['log_warning']['import_key_error' . $found_import_key]['message'])) {
            $context['results']['log_warning']['import_key_error' . $found_import_key]['message'] = t('Multiple users found with the same ' . $found_import_key .  ' at rows: ');
          }
          $context['results']['log_warning']['import_key_error' . $found_import_key]['rows'][] = (int)$offset+1;
          $context['results']['cnt_warning']++;
        }
      }
      if (!$found_user) {
        $context['results']['cnt_import']++;
        $user = User::create([
          'mail' => $user_data['mail'],
          'name' => $user_data['mail'],
          'status' => 1,
        ]);
        $store->set(crc32($user_data['mail']) . '_pending_finalization', true);
        $user->save();
        $data = time();
        $data .= $user->id();
        $data .= $user->getEmail();
        $hash_token =  Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
        $user->set('field_iq_group_user_token', $hash_token);
        $user->save();
      }

      // Load General group (id=5) and get the roles for the user.
      $generalGroup = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $generalGroup);
      $groupRoles = array_keys($groupRoles);
      if (!in_array('subscription-subscriber', $groupRoles) && !in_array('subscription-lead', $groupRoles)) {
        \Drupal\iq_group\Controller\UserController::addGroupRoleToUser($generalGroup, $user, 'subscription-subscriber');
        $eventDispatcher = \Drupal::service('event_dispatcher');
        $eventDispatcher->dispatch(\Drupal\iq_group\IqGroupEvents::USER_OPT_IN, new \Drupal\iq_group\Event\IqGroupEvent( $user));
      }

      // Set the keys that have to be processed to the the available columns in the import
      $fields_to_process = $field_keys;

      if (array_key_exists('tags', $fields_to_process)) {
        override_user_tags($user_data, $user, $options['tag_option'], $existing_terms, $found_user);
        unset($fields_to_process['tags']);
      }

      if (array_key_exists('preferences', $fields_to_process)) {
        $ids = UserController::set_user_reference_field($user_data, $user, $options['preference_option'], $preference_names, 'preferences', 'field_iq_group_preferences', $found_user);
        $user->set('field_iq_group_preferences', $ids);
        unset($fields_to_process['preferences']);
      }
      // set_user_preferences($user_data, $user, $options['preference_option'], $preference_names);
      if ($user->hasField('field_iq_group_products') && array_key_exists('products', $fields_to_process)) {
        $preference_option = $options['product_option'] == 'override_product' ? "override_preferences" : "not_override_preferences";
        $ids = UserController::set_user_reference_field($user_data, $user, $preference_option, $product_ids, 'products', 'field_iq_group_products', $found_user);
        $user->set('field_iq_group_products', $ids);
        unset($fields_to_process['products']);
        // set_user_products($user_data, $user, $options['preference_option'], $product_ids);
      }
      if ($user->hasField('field_iq_group_branches') && array_key_exists('branches', $fields_to_process)) {
        $ids = UserController::set_user_reference_field($user_data, $user, $options['preference_option'], $branch_ids, 'branches', 'field_iq_group_branches', $found_user);
        $user->set('field_iq_group_branches', $ids);
        unset($fields_to_process['branches']);
        // set_user_branches($user_data, $user, $options['preference_option'], $branch_ids);
      }

      \Drupal::moduleHandler()
        ->invokeAll('iq_group_reference_import', [&$user_data, $user, $options, &$fields_to_process, $found_user]);
      // Go through each of the fields and map them to the appropriate keys.
      // Prefill the address info from the user.
      $address_infos = $user->field_iq_user_base_address->getValue();
      $address_info = reset($address_infos);

      foreach ($fields_to_process as $field_key => $field_mapping) {
        if ($options['user_option'] == 'override_user') {
          if ($field_mapping == 'field_iq_user_base_address') {
            $address_info[$field_key] = $user_data[$field_key];
          }
          else {
            $user->set($field_mapping, $user_data[$field_key]);
          }
        }
        else if ($options['user_option'] == 'override_user_hidden_fields') {
          if ($found_user) {
            if (in_array($field_mapping, $hidden_fields)) {
              if ($field_mapping == 'field_iq_user_base_address') {
                $address_info[$field_key] = $user_data[$field_key];
              }
              else {
                $user->set($field_mapping, $user_data[$field_key]);
              }
            }
          }
          else {
            if ($field_mapping == 'field_iq_user_base_address') {
              $address_info[$field_key] = $user_data[$field_key];
            }
            else {
              $user->set($field_mapping, $user_data[$field_key]);
            }
          }
        }
        else if ($options['user_option'] == 'not_override_user') {
          if ($found_user) {
            // If user was existing already, just skip it.
            continue;
          }
          else {
            if ($field_mapping == 'field_iq_user_base_address') {
              $address_info[$field_key] = $user_data[$field_key];
            }
            else {
              $user->set($field_mapping, $user_data[$field_key]);
            }
          }
        }
        else {
          if ($field_mapping == 'field_iq_user_base_address') {
            $real_address = $user->get('field_iq_user_base_address')
              ->getValue();
            $address_info[$field_key] = strlen(trim($real_address[$field_key])) == 0 ? $user_data[$field_key] : $real_address[$field_key];
          }
          else {
            if (empty($user->get($field_mapping)->getValue())) {
              $user->set($field_mapping, $user_data[$field_key]);
            }
          }
        }
      }
      if (!empty($address_info)) {
        if (empty($address_info['country_code'])) {
          $address_info['country_code'] = 'CH';
        }
        $user->set('field_iq_user_base_address', $address_info);
      }
      $store->delete(crc32($user_data['mail']) . '_pending_finalization');
      $user->save();
      //update_user_groups($user);
      \Drupal::moduleHandler()->invokeAll('iq_group_after_import', [&$user]);
    }
  }


  $indexes = range($context['sandbox']['progress'], $context['sandbox']['progress']);

  foreach ($indexes as $i) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_index'] = $i;

    $processed_abs = $i + 1;
    $processed_rel = $processed_abs / ($context['sandbox']['max'] + 1) * 100;

    $context['message'] = 'Processed ' . $processed_abs . ' of ' . ($context['sandbox']['max'] + 1) . ' records (' . round($processed_rel) . ' %).';
  }

  if (!empty($context['sandbox']['max']) && !empty($context['sandbox']['progress']) && $context['sandbox']['progress'] != $context['sandbox']['max'] && $context['sandbox']['max'] != 0) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

function report_unused_fields($unused_keys, &$context) {
  \Drupal::messenger()->addMessage("Unused fields: " . json_encode($unused_keys));
}


function validateUser(&$user_data, $field_keys, $offset, &$context) {
  $required_fields = ['mail'];
  $valid = true;
  if (strpos($user_data['mail'], '@') < 1) {
    if (empty($context['result']['log_error'])) {
      $context['results']['log_error'] = [];
    }
    if (empty($context['results']['log_error']['invalid_email']) && !isset($context['results']['log_error']['invalid_email']['message'])) {
      $context['results']['log_error']['invalid_email']['message'] = t('Invalid email at rows: ');
    }
    $context['results']['log_error']['invalid_email']['rows'][] = (int)$offset+1;
    $context['results']['cnt_error']++;
    return false;
  }
  foreach ($required_fields as $required_field) {
    if (empty($user_data[$required_field])) {
      $valid = false;
      if (empty($context['results']['log_error']['required_field_missing']))
        $context['results']['log_error']['required_field_missing']['message'] = t('Required field missing at rows: ');
      $context['results']['log_error']['required_field_missing']['rows'][] = (int)$offset+1;
    }
  }

  if (!empty($user_data) && $valid) {
    return true;
  }
  else {
    return false;
  }
}

function override_user_tags(&$user_data, &$user, $option, $existing_terms, $found_user) {
  $tags = explode(',', $user_data['tags']);
  if ($option == 'not_override_tags' && $found_user) {
    unset($user_data['tags']);
    return ;
  }
  $user_tags = [];
  // @todo Go through the tags and check if they exists and create them if they do not exist.
  foreach ($tags as $tag) {
    if (in_array($tag, $existing_terms)) {
      $user_tags [] = ['target_id' => array_search($tag, $existing_terms)];
    }
    else {
      // If the tag does not exist, do not create it.
      // If needed, report in error message that the tag does not exist.
      /*$new_term = \Drupal\taxonomy\Entity\Term::create([
        'vid' => 'tags',
        'name' => $tag
      ]);
      $new_term->save();
      $user_tags[] = ['target_id' => $new_term->id()];*/
    }
  }

  // Set tags based on the tag override option.
  if ($option == 'override_tags') {
    $user_data['tags'] = $user_tags;
  }
  else {
    if ($option == 'add_tags') {
      $existing_tags = $user->get('field_iq_group_tags')->getValue();
      $existing_tags = array_filter(array_column($existing_tags, 'target_id'));
      $user_tags = array_filter(array_column($user_tags, 'target_id'));
      $user_data['tags'] = array_unique(array_merge($existing_tags, $user_tags));
    }
    else if ($option == 'remove_tags') {
      // Remove the tags that are listed in the import.
      $existing_tags = $user->get('field_iq_group_tags')->getValue();
      $existing_tags = array_filter(array_column($existing_tags, 'target_id'));
      $user_tags = array_filter(array_column($user_tags, 'target_id'));
      $user_data['tags'] = array_diff($existing_tags, $user_tags);
    }
  }
  $user->set('field_iq_group_tags', $user_data['tags']);
  unset($user_data['tags']);
}

function set_user_preferences(&$user_data, &$user, $option, $preference_names) {
  $ids = [];
  $user_data['preferences'] = explode(',', $user_data['preferences']);
  foreach ($user_data['preferences'] as $preference) {
    if (in_array(trim($preference), $preference_names)) {
      $ids[] = ['target_id' => (string)array_search(trim($preference), $preference_names)];
    }
  }
  // Set preferences based on the preference override option.
  if ($option == 'override_preferences') {
    $user_data['preferences'] = $ids;
  }
  else {
    if ($option == 'add_preferences') {
      $existing_preferences = $user->get('field_iq_group_preferences')->getValue();
      $ids = array_merge($existing_preferences, $ids);
    }
    else {
      $existing_preferences = $user->get('field_iq_group_preferences')->getValue();
      $existing_preferences = array_filter(array_column($existing_preferences, 'target_id'));
      $ids = array_filter(array_column($ids, 'target_id'));
      foreach ($ids as $delete_id) {
        unset($existing_preferences[array_search($delete_id, $existing_preferences)]);
      }
      $ids = $existing_preferences;
    }
  }
  $user_data['preferences'] = $ids;
}

function set_user_products(&$user_data, &$user, $option, $product_ids) {
  $ids = [];
  $user_data['products'] = explode(',', $user_data['products']);
  foreach ($user_data['products'] as $product) {
    if (in_array(trim($product), $product_ids)) {
      $ids[] = ['target_id' => (string)array_search(trim($product), $product_ids)];
    }
  }
  // Set preferences based on the preference override option.
  if ($option == 'override_preferences') {
    $user_data['products'] = $ids;
  }
  else {
    if ($option == 'add_preferences') {
      $existing_products = $user->get('field_iq_group_products')->getValue();
      $ids = array_merge($existing_products, $ids);
    }
    else {
      $existing_products = $user->get('field_iq_group_products')->getValue();
      $existing_products = array_filter(array_column($existing_products, 'target_id'));
      $ids = array_filter(array_column($ids, 'target_id'));
      foreach ($ids as $delete_id) {
        unset($existing_products[array_search($delete_id, $existing_products)]);
      }
      $ids = $existing_products;
    }
  }
  $user_data['products'] = $ids;
}

function set_user_branches(&$user_data, &$user, $option, $branch_ids) {
  $ids = [];
  $user_data['branches'] = explode(',', $user_data['branches']);
  foreach ($user_data['branches'] as $branch) {
    if (in_array(trim($branch), $branch_ids)) {
      $ids[] = ['target_id' => (string)array_search(trim($branch), $branch_ids)];
    }
  }
  // Set preferences based on the preference override option.
  if ($option == 'override_preferences') {
    $user_data['branches'] = $ids;
  }
  else {
    if ($option == 'add_preferences') {
      $existing_branches = $user->get('field_iq_group_branches')->getValue();
      $ids = array_merge($existing_branches, $ids);
    }
    else {
      $existing_branches = $user->get('field_iq_group_branches')->getValue();
      $existing_branches = array_filter(array_column($existing_branches, 'target_id'));
      $ids = array_filter(array_column($ids, 'target_id'));
      foreach ($ids as $delete_id) {
        unset($existing_branches[array_search($delete_id, $existing_branches)]);
      }
      $ids = $existing_branches;
    }
  }
  $user_data['branches'] = $ids;
}

function finished_import($success, $results, $operations) {
  $msg = '<p>Finished import, processed ' . ($results['cnt_import'] + $results['cnt_error'] + $results['cnt_update']) . ' records<p>';
  $rendered_msg = \Drupal\Core\Render\Markup::create($msg);
  \Drupal::messenger()->addMessage($rendered_msg);
  if ($results['cnt_error'] > 0) {
    $msg = '<p>Skipped ' . $results['cnt_error'] . ' records due to errors:</p>';
    foreach ($results['log_error'] as $error) {
      if (!empty($error['message']) && !empty($error['rows'])) {
        $msg .= '<ul>' . $error['message'] . implode(', ', $error['rows']) . '</ul>';
      }
    }
    $rendered_msg = \Drupal\Core\Render\Markup::create($msg);
    \Drupal::messenger()->addError($rendered_msg);
  }
  if ($results['cnt_warning'] > 0) {
    $msg = '<p>Warning on ' . $results['cnt_warning'] . ' records:</p>';
    foreach ($results['log_warning'] as $error) {
      if (!empty($error['message']) && !empty($error['rows'])) {
        $msg .= '<ul>' . $error['message'] . implode(', ', $error['rows']) . '</ul>';
      }
    }
    $rendered_msg = \Drupal\Core\Render\Markup::create($msg);
    \Drupal::messenger()->addWarning($rendered_msg);
  }
}

function update_user_groups(\Drupal\user\UserInterface $account) {
  $generalGroup = Group::load(\Drupal::config('iq_group.settings')
    ->get('general_group_id'));
  if (!empty($generalGroup)) {
    /*if (!$generalGroup->getMember($account)) {
      $generalGroup->addMember($account, ['group_roles' => ['subscription-subscriber']]);
    }*/
    // Get the selected preferences from the user.
    $selected_preferences = [];
    if ($account->hasField('field_iq_group_preferences')) {
      $selected_preferences = array_filter(array_column($account->field_iq_group_preferences->getValue(), 'target_id'));
    }
    // When updating the user, check for the preferences and
    // update groups accordingly.
    $result = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadMultiple();
    /**
     * @var  int $key
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $key => $group) {
      // If it is not the general group, check for updates.
      if ($group->id() != \Drupal::config('iq_group.settings')
          ->get('general_group_id')) {
        if (in_array($group->id(), $selected_preferences)) {
          /** @var \Drupal\group\Entity\GroupRoleInterface $generalGroupRole */
          $generalGroupRole = reset($generalGroup->getMember($account)
            ->getRoles());
          \Drupal\iq_group\Controller\UserController::addGroupRoleToUser($group, $account, $generalGroupRole->id());
        }
        else {
          $group->removeMember($account);
        }
      }
    }
  }
}
