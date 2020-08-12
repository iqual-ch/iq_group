<?php

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use League\Csv\Reader;

function csv_import($import_file_url, $i, $preference_names, $current_user, $options, $existing_terms, &$context)
{
  $reader = Reader::createFromPath($import_file_url, 'r');
  $reader->setDelimiter($options['delimiter']);
  $reader->setHeaderOffset(0);
  // From import form, when chunking the data.
  // Get user records.
  $stmt = (new \League\Csv\Statement())
    ->offset($i)
    ->limit(20);
  $records = $stmt->process($reader);
  $j = 1;

  // Field mapping.
  $field_mappings = [
    'given_name' => 'field_iq_user_base_address',
    'family_name' => 'field_iq_user_base_address',
    'preferences' => 'field_iq_group_preferences',
    'organization' => 'field_iq_user_base_address',
    'mail' => 'mail',
  ];
  if (empty($context['results'])) {
    $context['sandbox'] = ['set' => 'now it is set'];
    $context['sandbox']['progress'] = 0;
    $context['results'] = [];
    $context['results']['log_error'] = '';
    $context['results']['cnt_import'] = 0;
    $context['results']['cnt_update'] = 0;
    $context['results']['cnt_error'] = 0;
    $context['results']['processed_users'] = [];
    $context['results']['start'] = microtime(TRUE);
  }

  // Go through the 20 records.
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
      // Check if user already exists.
      $checkingUser = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties([
          'mail' => $user_data['mail']
        ]);

      // Get the existing user or create a new user.
      if (!empty($checkingUser)) {
        $user = reset($checkingUser);
        $context['results']['cnt_update']++;
      }
      else {
        $context['results']['cnt_import']++;
        $user = User::create([
          'mail' => $user_data['mail'],
          'name' => $user_data['mail'],
          'status' => 1,
        ]);
        $data = time();
        $data .= $user->id();
        $data .= $user->getEmail();
        $hash_token =  Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
        $user->set('field_iq_group_user_token', $hash_token);
        $user->save();
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
      }
      $tags = explode(',', $user_data['tags']);
      override_user_tags($tags, $user, $options['tag_option'], $existing_terms);
      set_user_preferences($user_data, $user, $options['preference_option'], $preference_names);

      // Go through each of the fields and map them to the appropriate keys.
      $address_info = [];

      foreach ($field_keys as $field_key => $field_mapping) {
        if ($options['user_option'] == 'override_user') {
          if ($field_mapping == 'field_iq_user_base_address') {
            $address_info[$field_key] = $user_data[$field_key];
          }
          else {
            $user->set($field_mapping, $user_data[$field_key]);
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
        $address_info['country_code'] = 'CH';
        $user->set('field_iq_user_base_address', $address_info);
      }
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

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
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
    if (empty($context['results']['log_error']['invalid_email']) && !isset($context['results']['log_error']['invalid_email']['message'])) {
      $context['results']['log_error'] = [];
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

function override_user_tags($tags, &$user, $option, $existing_terms) {
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
    $user->set('field_iq_group_tags', $user_tags);
  }
  else {
    if ($option == 'add_tags') {
      $existing_tags = $user->get('field_iq_group_tags')->getValue();
      $user->set('field_iq_group_tags', array_merge($existing_tags, $user_tags));
    }
    else {
      $existing_tags = $user->get('field_iq_group_tags')->getValue();
      $existing_tags = array_filter(array_column($existing_tags, 'target_id'));
      $user_tags = array_filter(array_column($user_tags, 'target_id'));
      $tag_ids = array_diff($existing_tags, $user_tags);
      $user->set('field_iq_group_tags', $tag_ids);
    }
  }
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

function finished_import($success, $results, $operations) {
  $msg = '<p>Finished import, processed ' . ($results['cnt_import'] + $results['cnt_error'] + $results['cnt_update']) . ' records<p>';
  $rendered_msg = \Drupal\Core\Render\Markup::create($msg);
  \Drupal::messenger()->addMessage($rendered_msg);
  if ($results['cnt_error'] > 0) {
    $msg = '<p>Skipped ' . $results['cnt_error'] . ' records due to errors:</p>';
    foreach ($results['log_error'] as $error) {
      $msg .= '<ul>' . $error['message'] . implode(', ', $error['rows']) . '</ul>';
    }
    $rendered_msg = \Drupal\Core\Render\Markup::create($msg);
    \Drupal::messenger()->addError($rendered_msg);
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