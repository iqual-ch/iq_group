<?php

namespace Drupal\iq_group;

/**
 * Contains all events thrown in the iq_group module.
 */
final class IqGroupEvents {

  /**
   * Name of the event fired after user opts in.
   */
  public const USER_OPT_IN = 'iq_group.userOptIn';

  /**
   * Name of the event fired user gets the lead role.
   */
  public const USER_PROMOTE_LEAD = 'iq_group.userPromoteLead';

  /**
   * Name of the event fired after the user edits profile.
   */
  public const USER_PROFILE_EDIT = 'iq_group.userProfileEdit';

  /**
   * Name of the event fired after a user profile is updated.
   */
  public const USER_PROFILE_UPDATE = 'iq_group.userProfileUpdate';

  /**
   * Name of the event fired before a user profile is deleted.
   */
  public const USER_PROFILE_DELETE = 'iq_group.userProfileDelete';

}
