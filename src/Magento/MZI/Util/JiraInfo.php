<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\MZI\Util;

class JiraInfo
{
    /**
     * Jira custom fields
     */
    const JIRA_FIELD_RELEASE_LINE = "customfield_14121";
    const JIRA_FIELD_SKIPPED_REASON = "customfield_14621";
    const JIRA_FIELD_TEST_TYPE = "customfield_13324";
    const JIRA_FIELD_STORIES = "customfield_14364";
    const JIRA_FIELD_SEVERITY = "customfield_12720";
    const JIRA_FIELD_GROUP = "customfield_14362";

    /**
     * List of all transitions from Open to Automated. Skip transition handled separately
     */
    public static $transitionStates = [
        "Open", "In Progress", "Ready for Review", "In Review", "Review Passed", "Automated"
    ];
}
