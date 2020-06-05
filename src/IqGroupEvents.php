<?php

namespace Drupal\iq_group;

/**
 *
 */
final class IqGroupEvents {

  /**
   * Name of the event fired after user opts in.
   */
  const USER_OPT_IN = 'iq_group.userOptIn';

  /**
   * Name of the event fired after user downloads a whitepaper.
   */
  const USER_DOWNLOAD_WHITEPAPER = 'iq_group.userDownloadWhitepaper';

  /**
   * Name of the event fired user gets the lead role.
   */
  const USER_PROMOTE_LEAD = 'iq_group.userPromoteLead';

  /**
   * Name of the event fired after the user edits profile.
   */
  const USER_PROFILE_EDIT = 'iq_group.userProfileEdit';

  /**
   * Name of the event fired after a user profile is updated.
   */
  const USER_PROFILE_UPDATE = 'iq_group.userProfileUpdate';

  /**
   * Name of the event fired before a user profile is deleted.
   */
  const USER_PROFILE_DELETE = 'iq_group.userProfileDelete';
}
