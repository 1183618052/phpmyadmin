<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Functions used for database and table tracking
 *
 * @package PhpMyAdmin
 */
namespace PhpMyAdmin;

use PhpMyAdmin\Core;
use PhpMyAdmin\Message;
use PhpMyAdmin\Relation;
use PhpMyAdmin\Response;
use PhpMyAdmin\Sanitize;
use PhpMyAdmin\SqlQueryForm;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tracker;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;

/**
 * PhpMyAdmin\Tracking class
 *
 * @package PhpMyAdmin
 */
class Tracking
{
    /**
     * Filters tracking entries
     *
     * @param array  $data           the entries to filter
     * @param string $filter_ts_from "from" date
     * @param string $filter_ts_to   "to" date
     * @param array  $filter_users   users
     *
     * @return array filtered entries
     */
    public static function filterTracking(
        array $data, $filter_ts_from, $filter_ts_to, array $filter_users
    ) {
        $tmp_entries = array();
        $id = 0;
        foreach ($data as $entry) {
            $timestamp = strtotime($entry['date']);
            $filtered_user = in_array($entry['username'], $filter_users);
            if ($timestamp >= $filter_ts_from
                && $timestamp <= $filter_ts_to
                && (in_array('*', $filter_users) || $filtered_user)
            ) {
                $tmp_entries[] = array(
                    'id'        => $id,
                    'timestamp' => $timestamp,
                    'username'  => $entry['username'],
                    'statement' => $entry['statement']
                );
            }
            $id++;
        }
        return($tmp_entries);
    }

    /**
     * Function to get html for data definition and data manipulation statements
     *
     * @param string $url_query    url query
     * @param int    $last_version last version
     * @param string $db           database
     * @param array  $selected     selected tables
     * @param string $type         type of the table; table, view or both
     *
     * @return string
     */
    public static function getHtmlForDataDefinitionAndManipulationStatements($url_query,
        $last_version, $db, array $selected, $type = 'both'
    ) {
        $html  = '<div id="div_create_version">';
        $html .= '<form method="post" action="' . $url_query . '">';
        $html .= Url::getHiddenInputs($db);
        foreach ($selected as $selected_table) {
            $html .= '<input type="hidden" name="selected[]"'
                . ' value="' . htmlspecialchars($selected_table) . '" />';
        }

        $html .= '<fieldset>';
        $html .= '<legend>';
        if (count($selected) == 1) {
            $html .= sprintf(
                __('Create version %1$s of %2$s'),
                ($last_version + 1),
                htmlspecialchars($db . '.' . $selected[0])
            );
        } else {
            $html .= sprintf(__('Create version %1$s'), ($last_version + 1));
        }
        $html .= '</legend>';
        $html .= '<input type="hidden" name="version" value="' . ($last_version + 1)
            . '" />';
        $html .= '<p>' . __('Track these data definition statements:')
            . '</p>';

        if ($type == 'both' || $type == 'table') {
            $html .= '<input type="checkbox" name="alter_table" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'ALTER TABLE'
                ) !== false ? ' checked="checked"' : '')
                . ' /> ALTER TABLE<br/>';
            $html .= '<input type="checkbox" name="rename_table" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'RENAME TABLE'
                ) !== false ? ' checked="checked"' : '')
                . ' /> RENAME TABLE<br/>';
            $html .= '<input type="checkbox" name="create_table" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'CREATE TABLE'
                ) !== false ? ' checked="checked"' : '')
                . ' /> CREATE TABLE<br/>';
            $html .= '<input type="checkbox" name="drop_table" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'DROP TABLE'
                ) !== false ? ' checked="checked"' : '')
                . ' /> DROP TABLE<br/>';
        }
        if ($type == 'both') {
            $html .= '<br/>';
        }
        if ($type == 'both' || $type == 'view') {
            $html .= '<input type="checkbox" name="alter_view" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'ALTER VIEW'
                ) !== false ? ' checked="checked"' : '')
                . ' /> ALTER VIEW<br/>';
            $html .= '<input type="checkbox" name="create_view" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'CREATE VIEW'
                ) !== false ? ' checked="checked"' : '')
                . ' /> CREATE VIEW<br/>';
            $html .= '<input type="checkbox" name="drop_view" value="true"'
                . (mb_stripos(
                    $GLOBALS['cfg']['Server']['tracking_default_statements'],
                    'DROP VIEW'
                ) !== false ? ' checked="checked"' : '')
                . ' /> DROP VIEW<br/>';
        }
        $html .= '<br/>';

        $html .= '<input type="checkbox" name="create_index" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'CREATE INDEX'
            ) !== false ? ' checked="checked"' : '')
            . ' /> CREATE INDEX<br/>';
        $html .= '<input type="checkbox" name="drop_index" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'DROP INDEX'
            ) !== false ? ' checked="checked"' : '')
            . ' /> DROP INDEX<br/>';
        $html .= '<p>' . __('Track these data manipulation statements:') . '</p>';
        $html .= '<input type="checkbox" name="insert" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'INSERT'
            ) !== false ? ' checked="checked"' : '')
            . ' /> INSERT<br/>';
        $html .= '<input type="checkbox" name="update" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'UPDATE'
            ) !== false ? ' checked="checked"' : '')
            . ' /> UPDATE<br/>';
        $html .= '<input type="checkbox" name="delete" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'DELETE'
            ) !== false ? ' checked="checked"' : '')
            . ' /> DELETE<br/>';
        $html .= '<input type="checkbox" name="truncate" value="true"'
            . (mb_stripos(
                $GLOBALS['cfg']['Server']['tracking_default_statements'],
                'TRUNCATE'
            ) !== false ? ' checked="checked"' : '')
            . ' /> TRUNCATE<br/>';
        $html .= '</fieldset>';

        $html .= '<fieldset class="tblFooters">';
        $html .= '<input type="hidden" name="submit_create_version" value="1" />';
        $html .= '<input type="submit" value="' . __('Create version') . '" />';
        $html .= '</fieldset>';

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Function to get html for activate/deactivate tracking
     *
     * @param string $action      activate|deactivate
     * @param string $urlQuery    url query
     * @param int    $lastVersion last version
     *
     * @return string HTML
     */
    public static function getHtmlForActivateDeactivateTracking(
        $action,
        $urlQuery,
        $lastVersion
    ) {
        return Template::get('table/tracking/activate_deactivate')->render([
            'action' => $action,
            'url_query' => $urlQuery,
            'last_version' => $lastVersion,
            'db' => $GLOBALS['db'],
            'table' => $GLOBALS['table'],
        ]);
    }

    /**
     * Function to get the list versions of the table
     *
     * @return array
     */
    public static function getListOfVersionsOfTable()
    {
        $cfgRelation = Relation::getRelationsParam();
        $sql_query = " SELECT * FROM " .
            Util::backquote($cfgRelation['db']) . "." .
            Util::backquote($cfgRelation['tracking']) .
            " WHERE db_name = '" . $GLOBALS['dbi']->escapeString($_REQUEST['db']) .
            "' " .
            " AND table_name = '" .
            $GLOBALS['dbi']->escapeString($_REQUEST['table']) . "' " .
            " ORDER BY version DESC ";

        return Relation::queryAsControlUser($sql_query);
    }

    /**
     * Function to get html for displaying last version number
     *
     * @param array  $sql_result    sql result
     * @param int    $last_version  last version
     * @param array  $url_params    url parameters
     * @param string $url_query     url query
     * @param string $pmaThemeImage path to theme's image folder
     * @param string $text_dir      text direction
     *
     * @return string
     */
    public static function getHtmlForTableVersionDetails(
        $sql_result, $last_version, array $url_params,
        $url_query, $pmaThemeImage, $text_dir
    ) {
        $tracking_active = false;

        $html  = '<form method="post" action="tbl_tracking.php" name="versionsForm"'
            . ' id="versionsForm" class="ajax">';
        $html .= Url::getHiddenInputs($GLOBALS['db'], $GLOBALS['table']);
        $html .= '<table id="versions" class="data">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th></th>';
        $html .= '<th>' . __('Version') . '</th>';
        $html .= '<th>' . __('Created') . '</th>';
        $html .= '<th>' . __('Updated') . '</th>';
        $html .= '<th>' . __('Status') . '</th>';
        $html .= '<th>' . __('Action') . '</th>';
        $html .= '<th>' . __('Show') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $GLOBALS['dbi']->dataSeek($sql_result, 0);
        $delete = Util::getIcon('b_drop', __('Delete version'));
        $report = Util::getIcon('b_report', __('Tracking report'));
        $structure = Util::getIcon('b_props', __('Structure snapshot'));

        while ($version = $GLOBALS['dbi']->fetchArray($sql_result)) {
            if ($version['version'] == $last_version) {
                if ($version['tracking_active'] == 1) {
                    $tracking_active = true;
                } else {
                    $tracking_active = false;
                }
            }
            $delete_link = 'tbl_tracking.php' . $url_query . '&amp;version='
                . htmlspecialchars($version['version'])
                . '&amp;submit_delete_version=true';
            $checkbox_id = 'selected_versions_' . htmlspecialchars($version['version']);

            $html .= '<tr>';
            $html .= '<td class="center">';
            $html .= '<input type="checkbox" name="selected_versions[]"'
                . ' class="checkall" id="' . $checkbox_id . '"'
                . ' value="' . htmlspecialchars($version['version']) . '"/>';
            $html .= '</td>';
            $html .= '<th class="floatright">';
            $html .= '<label for="' . $checkbox_id . '">'
                . htmlspecialchars($version['version']) . '</label>';
            $html .= '</th>';
            $html .= '<td>' . htmlspecialchars($version['date_created']) . '</td>';
            $html .= '<td>' . htmlspecialchars($version['date_updated']) . '</td>';
            $html .= '<td>' . self::getVersionStatus($version) . '</td>';
            $html .= '<td><a class="delete_version_anchor ajax"'
                . ' href="' . $delete_link . '" >' . $delete . '</a></td>';
            $html .= '<td><a href="tbl_tracking.php';
            $html .= Url::getCommon(
                $url_params + array(
                    'report' => 'true', 'version' => $version['version']
                )
            );
            $html .= '">' . $report . '</a>';
            $html .= '&nbsp;&nbsp;';
            $html .= '<a href="tbl_tracking.php';
            $html .= Url::getCommon(
                $url_params + array(
                    'snapshot' => 'true', 'version' => $version['version']
                )
            );
            $html .= '">' . $structure . '</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= Template::get('select_all')
            ->render(
                array(
                    'pma_theme_image' => $pmaThemeImage,
                    'text_dir'        => $text_dir,
                    'form_name'       => 'versionsForm',
                )
            );
        $html .= Util::getButtonOrImage(
            'submit_mult', 'mult_submit',
            __('Delete version'), 'b_drop', 'delete_version'
        );

        $html .= '</form>';

        if ($tracking_active) {
            $html .= self::getHtmlForActivateDeactivateTracking(
                'deactivate', $url_query, $last_version
            );
        } else {
            $html .= self::getHtmlForActivateDeactivateTracking(
                'activate', $url_query, $last_version
            );
        }

        return $html;
    }

    /**
     * Function to get the last version number of a table
     *
     * @param array $sql_result sql result
     *
     * @return int
     */
    public static function getTableLastVersionNumber($sql_result)
    {
        $maxversion = $GLOBALS['dbi']->fetchArray($sql_result);
        return intval($maxversion['version']);
    }

    /**
     * Function to get sql results for selectable tables
     *
     * @return array
     */
    public static function getSqlResultForSelectableTables()
    {
        $cfgRelation = Relation::getRelationsParam();

        $sql_query = " SELECT DISTINCT db_name, table_name FROM " .
            Util::backquote($cfgRelation['db']) . "." .
            Util::backquote($cfgRelation['tracking']) .
            " WHERE db_name = '" . $GLOBALS['dbi']->escapeString($GLOBALS['db']) .
            "' " .
            " ORDER BY db_name, table_name";

        return Relation::queryAsControlUser($sql_query);
    }

    /**
     * Function to get html for selectable table rows
     *
     * @param array  $selectable_tables_sql_result sql results for selectable rows
     * @param string $url_query                    url query
     *
     * @return string
     */
    public static function getHtmlForSelectableTables($selectable_tables_sql_result, $url_query)
    {
        $html = '<form method="post" action="tbl_tracking.php' . $url_query . '">';
        $html .= Url::getHiddenInputs($GLOBALS['db'], $GLOBALS['table']);
        $html .= '<select name="table" class="autosubmit">';
        while ($entries = $GLOBALS['dbi']->fetchArray($selectable_tables_sql_result)) {
            if (Tracker::isTracked($entries['db_name'], $entries['table_name'])) {
                $status = ' (' . __('active') . ')';
            } else {
                $status = ' (' . __('not active') . ')';
            }
            if ($entries['table_name'] == $_REQUEST['table']) {
                $s = ' selected="selected"';
            } else {
                $s = '';
            }
            $html .= '<option value="' . htmlspecialchars($entries['table_name'])
                . '"' . $s . '>' . htmlspecialchars($entries['db_name']) . ' . '
                . htmlspecialchars($entries['table_name']) . $status . '</option>'
                . "\n";
        }
        $html .= '</select>';
        $html .= '<input type="hidden" name="show_versions_submit" value="1" />';
        $html .= '</form>';

        return $html;
    }

    /**
     * Function to get html for tracking report and tracking report export
     *
     * @param string  $url_query        url query
     * @param array   $data             data
     * @param array   $url_params       url params
     * @param boolean $selection_schema selection schema
     * @param boolean $selection_data   selection data
     * @param boolean $selection_both   selection both
     * @param int     $filter_ts_to     filter time stamp from
     * @param int     $filter_ts_from   filter time stamp tp
     * @param array   $filter_users     filter users
     *
     * @return string
     */
    public static function getHtmlForTrackingReport($url_query, array $data, array $url_params,
        $selection_schema, $selection_data, $selection_both, $filter_ts_to,
        $filter_ts_from, array $filter_users
    ) {
        $html = '<h3>' . __('Tracking report')
            . '  [<a href="tbl_tracking.php' . $url_query . '">' . __('Close')
            . '</a>]</h3>';

        $html .= '<small>' . __('Tracking statements') . ' '
            . htmlspecialchars($data['tracking']) . '</small><br/>';
        $html .= '<br/>';

        list($str1, $str2, $str3, $str4, $str5) = self::getHtmlForElementsOfTrackingReport(
            $selection_schema, $selection_data, $selection_both
        );

        // Prepare delete link content here
        $drop_image_or_text = '';
        if (Util::showIcons('ActionLinksMode')) {
            $drop_image_or_text .= Util::getImage(
                'b_drop', __('Delete tracking data row from report')
            );
        }
        if (Util::showText('ActionLinksMode')) {
            $drop_image_or_text .= __('Delete');
        }

        /*
         *  First, list tracked data definition statements
         */
        if (count($data['ddlog']) == 0 && count($data['dmlog']) == 0) {
            $msg = Message::notice(__('No data'));
            $msg->display();
        }

        $html .= self::getHtmlForTrackingReportExportForm1(
            $data, $url_params, $selection_schema, $selection_data, $selection_both,
            $filter_ts_to, $filter_ts_from, $filter_users, $str1, $str2, $str3,
            $str4, $str5, $drop_image_or_text
        );

        $html .= self::getHtmlForTrackingReportExportForm2(
            $url_params, $str1, $str2, $str3, $str4, $str5
        );

        $html .= "<br/><br/><hr/><br/>\n";

        return $html;
    }

    /**
     * Generate HTML element for report form
     *
     * @param boolean $selection_schema selection schema
     * @param boolean $selection_data   selection data
     * @param boolean $selection_both   selection both
     *
     * @return array
     */
    public static function getHtmlForElementsOfTrackingReport(
        $selection_schema, $selection_data, $selection_both
    ) {
        $str1 = '<select name="logtype">'
            . '<option value="schema"'
            . ($selection_schema ? ' selected="selected"' : '') . '>'
            . __('Structure only') . '</option>'
            . '<option value="data"'
            . ($selection_data ? ' selected="selected"' : '') . '>'
            . __('Data only') . '</option>'
            . '<option value="schema_and_data"'
            . ($selection_both ? ' selected="selected"' : '') . '>'
            . __('Structure and data') . '</option>'
            . '</select>';
        $str2 = '<input type="text" name="date_from" value="'
            . htmlspecialchars($_REQUEST['date_from']) . '" size="19" />';
        $str3 = '<input type="text" name="date_to" value="'
            . htmlspecialchars($_REQUEST['date_to']) . '" size="19" />';
        $str4 = '<input type="text" name="users" value="'
            . htmlspecialchars($_REQUEST['users']) . '" />';
        $str5 = '<input type="hidden" name="list_report" value="1" />'
            . '<input type="submit" value="' . __('Go') . '" />';
        return array($str1, $str2, $str3, $str4, $str5);
    }

    /**
     * Generate HTML for export form
     *
     * @param array   $data               data
     * @param array   $url_params         url params
     * @param boolean $selection_schema   selection schema
     * @param boolean $selection_data     selection data
     * @param boolean $selection_both     selection both
     * @param int     $filter_ts_to       filter time stamp from
     * @param int     $filter_ts_from     filter time stamp tp
     * @param array   $filter_users       filter users
     * @param string  $str1               HTML for logtype select
     * @param string  $str2               HTML for "from date"
     * @param string  $str3               HTML for "to date"
     * @param string  $str4               HTML for user
     * @param string  $str5               HTML for "list report"
     * @param string  $drop_image_or_text HTML for image or text
     *
     * @return string HTML for form
     */
    public static function getHtmlForTrackingReportExportForm1(
        array $data, array $url_params, $selection_schema, $selection_data, $selection_both,
        $filter_ts_to, $filter_ts_from, array $filter_users, $str1, $str2, $str3,
        $str4, $str5, $drop_image_or_text
    ) {
        $ddlog_count = 0;

        $html = '<form method="post" action="tbl_tracking.php'
            . Url::getCommon(
                $url_params + array(
                    'report' => 'true', 'version' => $_REQUEST['version']
                )
            )
            . '">';
        $html .= Url::getHiddenInputs();

        $html .= sprintf(
            __('Show %1$s with dates from %2$s to %3$s by user %4$s %5$s'),
            $str1, $str2, $str3, $str4, $str5
        );

        if ($selection_schema || $selection_both && count($data['ddlog']) > 0) {
            list($temp, $ddlog_count) = self::getHtmlForDataDefinitionStatements(
                $data, $filter_users, $filter_ts_from, $filter_ts_to, $url_params,
                $drop_image_or_text
            );
            $html .= $temp;
            unset($temp);
        } //endif

        /*
         *  Secondly, list tracked data manipulation statements
         */
        if (($selection_data || $selection_both) && count($data['dmlog']) > 0) {
            $html .= self::getHtmlForDataManipulationStatements(
                $data, $filter_users, $filter_ts_from, $filter_ts_to, $url_params,
                $ddlog_count, $drop_image_or_text
            );
        }
        $html .= '</form>';
        return $html;
    }

    /**
     * Generate HTML for export form
     *
     * @param array  $url_params Parameters
     * @param string $str1       HTML for logtype select
     * @param string $str2       HTML for "from date"
     * @param string $str3       HTML for "to date"
     * @param string $str4       HTML for user
     * @param string $str5       HTML for "list report"
     *
     * @return string HTML for form
     */
    public static function getHtmlForTrackingReportExportForm2(
        array $url_params, $str1, $str2, $str3, $str4, $str5
    ) {
        $html = '<form method="post" action="tbl_tracking.php'
            . Url::getCommon(
                $url_params + array(
                    'report' => 'true', 'version' => $_REQUEST['version']
                )
            )
            . '">';
        $html .= Url::getHiddenInputs();
        $html .= sprintf(
            __('Show %1$s with dates from %2$s to %3$s by user %4$s %5$s'),
            $str1, $str2, $str3, $str4, $str5
        );
        $html .= '</form>';

        $html .= '<form class="disableAjax" method="post" action="tbl_tracking.php'
            . Url::getCommon(
                $url_params
                + array('report' => 'true', 'version' => $_REQUEST['version'])
            )
            . '">';
        $html .= Url::getHiddenInputs();
        $html .= '<input type="hidden" name="logtype" value="'
            . htmlspecialchars($_REQUEST['logtype']) . '" />';
        $html .= '<input type="hidden" name="date_from" value="'
            . htmlspecialchars($_REQUEST['date_from']) . '" />';
        $html .= '<input type="hidden" name="date_to" value="'
            . htmlspecialchars($_REQUEST['date_to']) . '" />';
        $html .= '<input type="hidden" name="users" value="'
            . htmlspecialchars($_REQUEST['users']) . '" />';

        $str_export1 = '<select name="export_type">'
            . '<option value="sqldumpfile">' . __('SQL dump (file download)')
            . '</option>'
            . '<option value="sqldump">' . __('SQL dump') . '</option>'
            . '<option value="execution" onclick="alert(\''
            . Sanitize::escapeJsString(
                __('This option will replace your table and contained data.')
            )
            . '\')">' . __('SQL execution') . '</option>' . '</select>';

        $str_export2 = '<input type="hidden" name="report_export" value="1" />'
            . '<input type="submit" value="' . __('Go') . '" />';

        $html .= "<br/>" . sprintf(__('Export as %s'), $str_export1)
            . $str_export2 . "<br/>";
        $html .= '</form>';
        return $html;
    }

    /**
     * Function to get html for data manipulation statements
     *
     * @param array  $data               data
     * @param array  $filter_users       filter users
     * @param int    $filter_ts_from     filter time staml from
     * @param int    $filter_ts_to       filter time stamp to
     * @param array  $url_params         url parameters
     * @param int    $ddlog_count        data definition log count
     * @param string $drop_image_or_text drop image or text
     *
     * @return string
     */
    public static function getHtmlForDataManipulationStatements(array $data, array $filter_users,
        $filter_ts_from, $filter_ts_to, array $url_params, $ddlog_count,
        $drop_image_or_text
    ) {
        // no need for the secondth returned parameter
        list($html,) = self::getHtmlForDataStatements(
            $data, $filter_users, $filter_ts_from, $filter_ts_to, $url_params,
            $drop_image_or_text, 'dmlog', __('Data manipulation statement'),
            $ddlog_count, 'dml_versions'
        );

        return $html;
    }

    /**
     * Function to get html for one data manipulation statement
     *
     * @param array  $entry              entry
     * @param array  $filter_users       filter users
     * @param int    $filter_ts_from     filter time stamp from
     * @param int    $filter_ts_to       filter time stamp to
     * @param int    $line_number        line number
     * @param array  $url_params         url parameters
     * @param int    $offset             line number offset
     * @param string $drop_image_or_text drop image or text
     * @param string $delete_param       parameter for delete
     *
     * @return string
     */
    public static function getHtmlForOneStatement(array $entry, array $filter_users,
        $filter_ts_from, $filter_ts_to, $line_number, array $url_params, $offset,
        $drop_image_or_text, $delete_param
    ) {
        $statement  = Util::formatSql($entry['statement'], true);
        $timestamp = strtotime($entry['date']);
        $filtered_user = in_array($entry['username'], $filter_users);
        $html = null;

        if ($timestamp >= $filter_ts_from
            && $timestamp <= $filter_ts_to
            && (in_array('*', $filter_users) || $filtered_user)
        ) {
            $html = '<tr class="noclick">';
            $html .= '<td class="right"><small>' . $line_number . '</small></td>';
            $html .= '<td><small>'
                . htmlspecialchars($entry['date']) . '</small></td>';
            $html .= '<td><small>'
                . htmlspecialchars($entry['username']) . '</small></td>';
            $html .= '<td>' . $statement . '</td>';
            $html .= '<td class="nowrap"><a  class="delete_entry_anchor ajax"'
                . ' href="tbl_tracking.php'
                . Url::getCommon(
                    $url_params + array(
                        'report' => 'true',
                        'version' => $_REQUEST['version'],
                        $delete_param => ($line_number - $offset),
                    )
                )
                . '">'
                . $drop_image_or_text
                . '</a></td>';
            $html .= '</tr>';
        }

        return $html;
    }
    /**
     * Function to get html for data definition statements in schema snapshot
     *
     * @param array  $data               data
     * @param array  $filter_users       filter users
     * @param int    $filter_ts_from     filter time stamp from
     * @param int    $filter_ts_to       filter time stamp to
     * @param array  $url_params         url parameters
     * @param string $drop_image_or_text drop image or text
     *
     * @return array
     */
    public static function getHtmlForDataDefinitionStatements(array $data, array $filter_users,
        $filter_ts_from, $filter_ts_to, array $url_params, $drop_image_or_text
    ) {
        list($html, $line_number) = self::getHtmlForDataStatements(
            $data, $filter_users, $filter_ts_from, $filter_ts_to, $url_params,
            $drop_image_or_text, 'ddlog', __('Data definition statement'),
            1, 'ddl_versions'
        );

        return array($html, $line_number);
    }

    /**
     * Function to get html for data statements in schema snapshot
     *
     * @param array  $data               data
     * @param array  $filter_users       filter users
     * @param int    $filter_ts_from     filter time stamp from
     * @param int    $filter_ts_to       filter time stamp to
     * @param array  $url_params         url parameters
     * @param string $drop_image_or_text drop image or text
     * @param string $which_log          dmlog|ddlog
     * @param string $header_message     message for this section
     * @param int    $line_number        line number
     * @param string $table_id           id for the table element
     *
     * @return array
     */
    public static function getHtmlForDataStatements(array $data, array $filter_users,
        $filter_ts_from, $filter_ts_to, array $url_params, $drop_image_or_text,
        $which_log, $header_message, $line_number, $table_id
    ) {
        $offset = $line_number;
        $html  = '<table id="' . $table_id . '" class="data" width="100%">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th width="18">#</th>';
        $html .= '<th width="100">' . __('Date') . '</th>';
        $html .= '<th width="60">' . __('Username') . '</th>';
        $html .= '<th>' . $header_message . '</th>';
        $html .= '<th>' . __('Action') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($data[$which_log] as $entry) {
            $html .= self::getHtmlForOneStatement(
                $entry, $filter_users, $filter_ts_from, $filter_ts_to,
                $line_number, $url_params, $offset, $drop_image_or_text,
                'delete_' . $which_log
            );
            $line_number++;
        }
        $html .= '</tbody>';
        $html .= '</table>';

        return array($html, $line_number);
    }

    /**
     * Function to get html for schema snapshot
     *
     * @param string $url_query url query
     *
     * @return string
     */
    public static function getHtmlForSchemaSnapshot($url_query)
    {
        $html = '<h3>' . __('Structure snapshot')
            . '  [<a href="tbl_tracking.php' . $url_query . '">' . __('Close')
            . '</a>]</h3>';
        $data = Tracker::getTrackedData(
            $_REQUEST['db'], $_REQUEST['table'], $_REQUEST['version']
        );

        // Get first DROP TABLE/VIEW and CREATE TABLE/VIEW statements
        $drop_create_statements = $data['ddlog'][0]['statement'];

        if (mb_strstr($data['ddlog'][0]['statement'], 'DROP TABLE')
            || mb_strstr($data['ddlog'][0]['statement'], 'DROP VIEW')
        ) {
            $drop_create_statements .= $data['ddlog'][1]['statement'];
        }
        // Print SQL code
        $html .= Util::getMessage(
            sprintf(
                __('Version %s snapshot (SQL code)'),
                htmlspecialchars($_REQUEST['version'])
            ),
            $drop_create_statements
        );

        // Unserialize snapshot
        $temp = Core::safeUnserialize($data['schema_snapshot']);
        if ($temp === null) {
            $temp = array('COLUMNS' => array(), 'INDEXES' => array());
        }
        $columns = $temp['COLUMNS'];
        $indexes = $temp['INDEXES'];
        $html .= self::getHtmlForColumns($columns);

        if (count($indexes) > 0) {
            $html .= self::getHtmlForIndexes($indexes);
        } // endif
        $html .= '<br /><hr /><br />';

        return $html;
    }

    /**
     * Function to get html for displaying columns in the schema snapshot
     *
     * @param array $columns columns
     *
     * @return string
     */
    public static function getHtmlForColumns(array $columns)
    {
        return Template::get('table/tracking/structure_snapshot_columns')->render([
            'columns' => $columns,
        ]);
    }

    /**
     * Function to get html for the indexes in schema snapshot
     *
     * @param array $indexes indexes
     *
     * @return string
     */
    public static function getHtmlForIndexes(array $indexes)
    {
        return Template::get('table/tracking/structure_snapshot_indexes')->render([
            'indexes' => $indexes,
        ]);;
    }

    /**
     * Function to handle the tracking report
     *
     * @param array &$data tracked data
     *
     * @return string HTML for the message
     */
    public static function deleteTrackingReportRows(array &$data)
    {
        $html = '';
        if (isset($_REQUEST['delete_ddlog'])) {
            // Delete ddlog row data
            $html .= self::deleteFromTrackingReportLog(
                $data,
                'ddlog',
                'DDL',
                __('Tracking data definition successfully deleted')
            );
        }

        if (isset($_REQUEST['delete_dmlog'])) {
            // Delete dmlog row data
            $html .= self::deleteFromTrackingReportLog(
                $data,
                'dmlog',
                'DML',
                __('Tracking data manipulation successfully deleted')
            );
        }
        return $html;
    }

    /**
     * Function to delete from a tracking report log
     *
     * @param array  &$data     tracked data
     * @param string $which_log ddlog|dmlog
     * @param string $type      DDL|DML
     * @param string $message   success message
     *
     * @return string HTML for the message
     */
    public static function deleteFromTrackingReportLog(array &$data, $which_log, $type, $message)
    {
        $html = '';
        $delete_id = $_REQUEST['delete_' . $which_log];

        // Only in case of valid id
        if ($delete_id == (int)$delete_id) {
            unset($data[$which_log][$delete_id]);

            $successfullyDeleted = Tracker::changeTrackingData(
                $_REQUEST['db'],
                $_REQUEST['table'],
                $_REQUEST['version'],
                $type,
                $data[$which_log]
            );
            if ($successfullyDeleted) {
                $msg = Message::success($message);
            } else {
                $msg = Message::rawError(__('Query error'));
            }
            $html .= $msg->getDisplay();
        }
        return $html;
    }

    /**
     * Function to export as sql dump
     *
     * @param array $entries entries
     *
     * @return string HTML SQL query form
     */
    public static function exportAsSqlDump(array $entries)
    {
        $html = '';
        $new_query = "# "
            . __(
                'You can execute the dump by creating and using a temporary database. '
                . 'Please ensure that you have the privileges to do so.'
            )
            . "\n"
            . "# " . __('Comment out these two lines if you do not need them.') . "\n"
            . "\n"
            . "CREATE database IF NOT EXISTS pma_temp_db; \n"
            . "USE pma_temp_db; \n"
            . "\n";

        foreach ($entries as $entry) {
            $new_query .= $entry['statement'];
        }
        $msg = Message::success(
            __('SQL statements exported. Please copy the dump or execute it.')
        );
        $html .= $msg->getDisplay();

        $db_temp = $GLOBALS['db'];
        $table_temp = $GLOBALS['table'];

        $GLOBALS['db'] = $GLOBALS['table'] = '';

        $html .= SqlQueryForm::getHtml($new_query, 'sql');

        $GLOBALS['db'] = $db_temp;
        $GLOBALS['table'] = $table_temp;

        return $html;
    }

    /**
     * Function to export as sql execution
     *
     * @param array $entries entries
     *
     * @return array
     */
    public static function exportAsSqlExecution(array $entries)
    {
        $sql_result = array();
        foreach ($entries as $entry) {
            $sql_result = $GLOBALS['dbi']->query("/*NOTRACK*/\n" . $entry['statement']);
        }

        return $sql_result;
    }

    /**
     * Function to export as entries
     *
     * @param array $entries entries
     *
     * @return void
     */
    public static function exportAsFileDownload(array $entries)
    {
        @ini_set('url_rewriter.tags', '');

        // Replace all multiple whitespaces by a single space
        $table = htmlspecialchars(preg_replace('/\s+/', ' ', $_REQUEST['table']));
        $dump = "# " . sprintf(
            __('Tracking report for table `%s`'), $table
        )
        . "\n" . "# " . date('Y-m-d H:i:s') . "\n";
        foreach ($entries as $entry) {
            $dump .= $entry['statement'];
        }
        $filename = 'log_' . $table . '.sql';
        Response::getInstance()->disable();
        Core::downloadHeader(
            $filename,
            'text/x-sql',
            strlen($dump)
        );
        echo $dump;

        exit();
    }

    /**
     * Function to activate or deactivate tracking
     *
     * @param string $action activate|deactivate
     *
     * @return string HTML for the success message
     */
    public static function changeTracking($action)
    {
        $html = '';
        if ($action == 'activate') {
            $method = 'activateTracking';
            $message = __('Tracking for %1$s was activated at version %2$s.');
        } else {
            $method = 'deactivateTracking';
            $message = __('Tracking for %1$s was deactivated at version %2$s.');
        }
        $status = Tracker::$method(
            $GLOBALS['db'], $GLOBALS['table'], $_REQUEST['version']
        );
        if ($status) {
            $msg = Message::success(
                sprintf(
                    $message,
                    htmlspecialchars($GLOBALS['db'] . '.' . $GLOBALS['table']),
                    htmlspecialchars($_REQUEST['version'])
                )
            );
            $html .= $msg->getDisplay();
        }

        return $html;
    }

    /**
     * Function to get tracking set
     *
     * @return string
     */
    public static function getTrackingSet()
    {
        $tracking_set = '';

        // a key is absent from the request if it has been removed from
        // tracking_default_statements in the config
        if (isset($_REQUEST['alter_table']) && $_REQUEST['alter_table'] == true) {
            $tracking_set .= 'ALTER TABLE,';
        }
        if (isset($_REQUEST['rename_table']) && $_REQUEST['rename_table'] == true) {
            $tracking_set .= 'RENAME TABLE,';
        }
        if (isset($_REQUEST['create_table']) && $_REQUEST['create_table'] == true) {
            $tracking_set .= 'CREATE TABLE,';
        }
        if (isset($_REQUEST['drop_table']) && $_REQUEST['drop_table'] == true) {
            $tracking_set .= 'DROP TABLE,';
        }
        if (isset($_REQUEST['alter_view']) && $_REQUEST['alter_view'] == true) {
            $tracking_set .= 'ALTER VIEW,';
        }
        if (isset($_REQUEST['create_view']) && $_REQUEST['create_view'] == true) {
            $tracking_set .= 'CREATE VIEW,';
        }
        if (isset($_REQUEST['drop_view']) && $_REQUEST['drop_view'] == true) {
            $tracking_set .= 'DROP VIEW,';
        }
        if (isset($_REQUEST['create_index']) && $_REQUEST['create_index'] == true) {
            $tracking_set .= 'CREATE INDEX,';
        }
        if (isset($_REQUEST['drop_index']) && $_REQUEST['drop_index'] == true) {
            $tracking_set .= 'DROP INDEX,';
        }
        if (isset($_REQUEST['insert']) && $_REQUEST['insert'] == true) {
            $tracking_set .= 'INSERT,';
        }
        if (isset($_REQUEST['update']) && $_REQUEST['update'] == true) {
            $tracking_set .= 'UPDATE,';
        }
        if (isset($_REQUEST['delete']) && $_REQUEST['delete'] == true) {
            $tracking_set .= 'DELETE,';
        }
        if (isset($_REQUEST['truncate']) && $_REQUEST['truncate'] == true) {
            $tracking_set .= 'TRUNCATE,';
        }
        $tracking_set = rtrim($tracking_set, ',');

        return $tracking_set;
    }

    /**
     * Deletes a tracking version
     *
     * @param string $version tracking version
     *
     * @return string HTML of the success message
     */
    public static function deleteTrackingVersion($version)
    {
        $html = '';
        $versionDeleted = Tracker::deleteTracking(
            $GLOBALS['db'],
            $GLOBALS['table'],
            $version
        );
        if ($versionDeleted) {
            $msg = Message::success(
                sprintf(
                    __('Version %1$s of %2$s was deleted.'),
                    htmlspecialchars($version),
                    htmlspecialchars($GLOBALS['db'] . '.' . $GLOBALS['table'])
                )
            );
            $html .= $msg->getDisplay();
        }

        return $html;
    }

    /**
     * Function to create the tracking version
     *
     * @return string HTML of the success message
     */
    public static function createTrackingVersion()
    {
        $html = '';
        $tracking_set = self::getTrackingSet();

        $versionCreated = Tracker::createVersion(
            $GLOBALS['db'],
            $GLOBALS['table'],
            $_REQUEST['version'],
            $tracking_set,
            $GLOBALS['dbi']->getTable($GLOBALS['db'], $GLOBALS['table'])->isView()
        );
        if ($versionCreated) {
            $msg = Message::success(
                sprintf(
                    __('Version %1$s was created, tracking for %2$s is active.'),
                    htmlspecialchars($_REQUEST['version']),
                    htmlspecialchars($GLOBALS['db'] . '.' . $GLOBALS['table'])
                )
            );
            $html .= $msg->getDisplay();
        }

        return $html;
    }

    /**
     * Create tracking version for multiple tables
     *
     * @param array $selected list of selected tables
     *
     * @return void
     */
    public static function createTrackingForMultipleTables(array $selected)
    {
        $tracking_set = self::getTrackingSet();

        foreach ($selected as $selected_table) {
            Tracker::createVersion(
                $GLOBALS['db'],
                $selected_table,
                $_REQUEST['version'],
                $tracking_set,
                $GLOBALS['dbi']->getTable($GLOBALS['db'], $selected_table)->isView()
            );
        }
    }

    /**
     * Function to get the entries
     *
     * @param array $data           data
     * @param int   $filter_ts_from filter time stamp from
     * @param int   $filter_ts_to   filter time stamp to
     * @param array $filter_users   filter users
     *
     * @return array
     */
    public static function getEntries(array $data, $filter_ts_from, $filter_ts_to, array $filter_users)
    {
        $entries = array();
        // Filtering data definition statements
        if ($_REQUEST['logtype'] == 'schema'
            || $_REQUEST['logtype'] == 'schema_and_data'
        ) {
            $entries = array_merge(
                $entries,
                self::filterTracking(
                    $data['ddlog'], $filter_ts_from, $filter_ts_to, $filter_users
                )
            );
        }

        // Filtering data manipulation statements
        if ($_REQUEST['logtype'] == 'data'
            || $_REQUEST['logtype'] == 'schema_and_data'
        ) {
            $entries = array_merge(
                $entries,
                self::filterTracking(
                    $data['dmlog'], $filter_ts_from, $filter_ts_to, $filter_users
                )
            );
        }

        // Sort it
        $ids = $timestamps = $usernames = $statements = array();
        foreach ($entries as $key => $row) {
            $ids[$key]        = $row['id'];
            $timestamps[$key] = $row['timestamp'];
            $usernames[$key]  = $row['username'];
            $statements[$key] = $row['statement'];
        }

        array_multisort(
            $timestamps, SORT_ASC, $ids, SORT_ASC, $usernames,
            SORT_ASC, $statements, SORT_ASC, $entries
        );

        return $entries;
    }

    /**
     * Function to get version status
     *
     * @param array $version version info
     *
     * @return string $version_status The status message
     */
    public static function getVersionStatus(array $version)
    {
        if ($version['tracking_active'] == 1) {
            return __('active');
        } else {
            return __('not active');
        }
    }

    /**
     * Get HTML for untracked tables
     *
     * @param string $db              current database
     * @param array  $untrackedTables untracked tables
     * @param string $urlQuery        url query string
     * @param string $pmaThemeImage   path to theme's image folder
     * @param string $textDir         text direction
     *
     * @return string HTML
     */
    public static function getHtmlForUntrackedTables(
        $db,
        array $untrackedTables,
        $urlQuery,
        $pmaThemeImage,
        $textDir
    ) {
        return Template::get('database/tracking/untracked_tables')->render([
            'db' => $db,
            'untracked_tables' => $untrackedTables,
            'url_query' => $urlQuery,
            'pma_theme_image' => $pmaThemeImage,
            'text_dir' => $textDir,
        ]);
    }

    /**
     * Helper function: Recursive function for getting table names from $table_list
     *
     * @param array   $table_list Table list
     * @param string  $db         Current database
     * @param boolean $testing    Testing
     *
     * @return array $untracked_tables
     */
    public static function extractTableNames(array $table_list, $db, $testing = false)
    {
        $untracked_tables = array();
        $sep = $GLOBALS['cfg']['NavigationTreeTableSeparator'];

        foreach ($table_list as $key => $value) {
            if (is_array($value) && array_key_exists(('is' . $sep . 'group'), $value)
                && $value['is' . $sep . 'group']
            ) {
                $untracked_tables = array_merge(self::extractTableNames($value, $db), $untracked_tables); //Recursion step
            }
            else {
                if (is_array($value) && ($testing || Tracker::getVersion($db, $value['Name']) == -1)) {
                    $untracked_tables[] = $value['Name'];
                }
            }
        }
        return $untracked_tables;
    }


    /**
     * Get untracked tables
     *
     * @param string $db current database
     *
     * @return array $untracked_tables
     */
    public static function getUntrackedTables($db)
    {
        $table_list = Util::getTableList($db);
        $untracked_tables = self::extractTableNames($table_list, $db);  //Use helper function to get table list recursively.
        return $untracked_tables;
    }

    /**
     * Get tracked tables
     *
     * @param string $db              current database
     * @param object $allTablesResult result set of tracked tables
     * @param string $urlQuery        url query string
     * @param string $pmaThemeImage   path to theme's image folder
     * @param string $textDir         text direction
     * @param array  $cfgRelation     configuration storage info
     *
     * @return string HTML
     */
    public static function getHtmlForTrackedTables(
        $db,
        $allTablesResult,
        $urlQuery,
        $pmaThemeImage,
        $textDir,
        array $cfgRelation
    ) {
        $versions = [];
        while ($oneResult = $GLOBALS['dbi']->fetchArray($allTablesResult)) {
            list($tableName, $versionNumber) = $oneResult;
            $tableQuery = ' SELECT * FROM ' .
                 Util::backquote($cfgRelation['db']) . '.' .
                 Util::backquote($cfgRelation['tracking']) .
                 ' WHERE `db_name` = \''
                 . $GLOBALS['dbi']->escapeString($_REQUEST['db'])
                 . '\' AND `table_name`  = \''
                 . $GLOBALS['dbi']->escapeString($tableName)
                 . '\' AND `version` = \'' . $versionNumber . '\'';

            $tableResult = Relation::queryAsControlUser($tableQuery);
            $versionData = $GLOBALS['dbi']->fetchArray($tableResult);
            $versionData['status_button'] = self::getStatusButton(
                $versionData,
                $urlQuery
            );
            $versions[] = $versionData;
        }
        return Template::get('database/tracking/tracked_tables')->render([
            'db' => $db,
            'versions' => $versions,
            'url_query' => $urlQuery,
            'text_dir' => $textDir,
            'pma_theme_image' => $pmaThemeImage,
        ]);
    }

    /**
     * Get tracking status button
     *
     * @param array  $versionData data about tracking versions
     * @param string $urlQuery    url query string
     *
     * @return string HTML
     */
    private static function getStatusButton(array $versionData, $urlQuery)
    {
        $state = self::getVersionStatus($versionData);
        $options = array(
            0 => array(
                'label' => __('not active'),
                'value' => 'deactivate_now',
                'selected' => ($state != 'active')
            ),
            1 => array(
                'label' => __('active'),
                'value' => 'activate_now',
                'selected' => ($state == 'active')
            )
        );
        $link = 'tbl_tracking.php' . $urlQuery . '&amp;table='
            . htmlspecialchars($versionData['table_name'])
            . '&amp;version=' . $versionData['version'];

        return Util::toggleButton(
            $link,
            'toggle_activation',
            $options,
            null
        );
    }
}
