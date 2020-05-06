<?php

namespace Drupal\iq_group;

/**
 *
 */
final class MauticEvents {

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
}
