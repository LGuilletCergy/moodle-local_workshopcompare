<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Compare workshop grades (given and received)
 *
 * @package   local_workshopcompare
 * @copyright 2018 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : lib.php
 * Library file.
 */

/**
 * Calls the plugin during any page load.
 * @param settings_navigation $nav
 * @param context $context
 */

function local_workshopcompare_extend_navigation($workshopcomparenode, $course, $module, $cm) {

    global $CFG, $DB, $PAGE;

    $pagepath = $PAGE->url->get_path();

    // On ne teste pas /mod car strpos renvoie 0 si il trouve en première position.

    $workshopviewurl = "mod/workshop/view.php";

    $stop = strpos(http_build_query($_GET) , '&');
    if ($stop) {

        $length = $stop - 3;
        $id = substr(http_build_query($_GET), 3, $length);
    } else {

        $id = substr(http_build_query($_GET), 3);
    }

    $isworkshoppage = strpos($pagepath , $workshopviewurl);

    $context = context_course::instance($course->id);

    echo "<div style='display:none'>Pagepath = $pagepath Isworkshop = $isworkshoppage</div>";

    if ($isworkshoppage && has_capability('local/workshopcompare:view', $context)) {

        $coursemodule = $DB->get_record('course_modules', array('id' => $id));

        $workshopphase = $DB->get_record('workshop', array ('id' => $coursemodule->instance))->phase;

        if ($workshopphase >= 30) {

            $pluginurl = new moodle_url($CFG->wwwroot."/local/workshopcompare/compare.php", array('id' => $id));

            echo "<div style='display:none' id='hiddenworkshopcompare'><div style=text-align:center><a href=$pluginurl>"
                    . "<input type='button' class='btn btn-primary' value='".
                    get_string('pluginname', 'local_workshopcompare')."'></input></a></div></div>";
        }
    }
}