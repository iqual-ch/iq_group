<?php
$users = user_load_multiple();

foreach ($users as $user) {
  // If it is not the Guest (724) user.
  // Map all the user base fields.
  $user->set('field_iq_user_base_profile', $user->get('field_iq_group_profile_picture')->getValue());
  $user->set('field_iq_user_base_address', [
    'country_code' => 'CH',
    'given_name' => $user->field_iq_group_first_name->value ? $user->field_iq_group_first_name->value : "",
    'family_name' => $user->field_iq_group_last_name->value ? $user->field_iq_group_last_name->value : "",
  ]);
  $user->save();
}