<?php

/**
 * @file
 * Hooks related to iq_group and its plugins.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\user\UserInterface;

/**
 * Alter the data before importing users.
 *
 * @param array $data
 *   The user data to be altered before the import.
 */
function hook_iq_group_before_import(array &$data) {
  // Here others will make a module that will call this to alter "$data".
}

/**
 * Alter the data after importing users.
 *
 * @param array $data
 *   The user data to be altered after the import.
 */
function hook_iq_group_after_import(array &$data) {
  // Here others will make a module that will call this to alter "$data".
}

/**
 * Alter the data during the import of a user.
 *
 * @param array $data
 *   The user data to be altered after the import.
 * @param \Drupal\user\UserInterface $user
 *   The user that is being imported.
 * @param string $option
 *   The preference options that are chosen during the import.
 * @param array $field_keys
 *   The field mappings for the user data.
 * @param bool $found_user
 *   Whether a matching user was found or not.
 */
function hook_iq_group_reference_import(array &$data, UserInterface $user, $option, array &$field_keys, bool $found_user) {
  // Here others will make a module that will call this to alter "$data".
}

/**
 * @} End of "addtogroup hooks".
 */
