<?php

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the data before importing users.
 *
 * @param array $data
 *   The user data to be altered before the import.
 */
function hook_iq_group_before_import(array &$data) {
  // here others will make a module that will call this to alter "$data"
}

/**
 * Alter the data after importing users.
 *
 * @param array $data
 *   The user data to be altered after the import.
 */
function hook_iq_group_after_import(array &$data) {
  // here others will make a module that will call this to alter "$data"
}

/**
 * @} End of "addtogroup hooks".
 */
