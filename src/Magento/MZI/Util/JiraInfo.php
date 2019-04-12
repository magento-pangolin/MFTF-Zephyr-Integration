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
     * Jira issuelinks field
     */
    const JIRA_FIELD_ISSUE_LINKS = "issuelinks";
    const JIRA_FIELD_INWARD_ISSUE = "inwardIssue";
    const JIRA_FIELD_INWARD = "inward";
    const JIRA_FIELD_OUTWARD_ISSUE = "outwardIssue";
    const JIRA_FIELD_OUTWARD = "outward";

    /**
     * Jira test type for mftf
     */
    const JIRA_TEST_TYPE_MFTF = "MFTF Test";

    /**
     * Jira labels
     */
    const JIRA_LABEL_MTF_TO_MFTF = "mtf-to-mftf";
    const JIRA_LABEL_PWA = "PWA";

    /**
     * List of all transitions from Open to Automated. Skip transition handled separately
     */
    public static $transitionStates = [
        "Open", "In Progress", "Ready for Review", "In Review", "Review Passed", "Automated"
    ];

    /**
     * keyword to component map (only used when exact name match is not found)
     */
    public static $keywordToComponentMap = [
        "pagebuilder" => "Module/ PageBuilder",

    ];
}
