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
 * File : compare.php
 * Main file.
 */

require_once('../../config.php');
require_once("$CFG->libdir/csvlib.class.php");
require_once('../../mod/workshop/locallib.php');
//require_once($CFG->libdir.'/completionlib.php');

$id = required_param('id', PARAM_INT); // course_module ID
$download = optional_param('download', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('workshop', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $workshoprecord = $DB->get_record('workshop', array('id' => $cm->instance), '*', MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('local/workshopcompare:view', $PAGE->context);

$workshop = new workshop($workshoprecord, $cm, $course);

if (!$download) {

    $PAGE->set_title($workshop->name);
    $PAGE->set_heading($course->fullname);

    $output = $PAGE->get_renderer('mod_workshop');

    // Output starts here

    echo $output->header();
    echo $output->heading_with_help(format_string($workshop->name), 'userplan', 'workshop');

    //Tableau des notes attribuées
    echo "<div style=text-align:center><h2>Tableau des notes attribuées</h2></div>";

    $downloadurl1 = new moodle_url('compare.php', array('id' => $id, 'download' => 1));

    echo "<div style=text-align:center><a href=$downloadurl1><input type='button' class='btn btn-primary' value='".
            get_string('downloadcsv', 'local_workshopcompare')."'></input></a></div>";

    //Tableau des notes reçues
    echo "<br><br><div style=text-align:center><h2>Tableau des notes reçues</h2></div>";

    $downloadurl2 = new moodle_url('compare.php', array('id' => $id, 'download' => 2));

    echo "<div style=text-align:center><a href=$downloadurl2><input type='button' class='btn btn-primary' value='".
            get_string('downloadcsv', 'local_workshopcompare')."'></input></a></div>";

    // Rajouter un retour à l'atelier.

    $returnurl = new moodle_url($CFG->wwwroot."/mod/workshop/view.php", array('id' => $id));

    echo "<div style=text-align:center><br><br><br><a href=$returnurl><input type='button' class='btn btn-primary' value='".
            get_string('returnbutton', 'local_workshopcompare')."' style='text-align:center'></input></a></div>";





    echo $output->footer();

} else if ($download == 1) {

    //Liste des correcteurs
    $sql = "SELECT DISTINCT reviewer.id, reviewer.firstname, reviewer.lastname "
            . "FROM mdl_workshop_assessments correction, mdl_workshop_submissions copie, mdl_user reviewer "
            . "WHERE copie.workshopid = $workshop->id AND correction.submissionid = copie.id AND"
            . " correction.reviewerid = reviewer.id ORDER BY lastname, firstname";
    $reviewers = $DB->get_recordset_sql($sql);

    $csvreviewer = new csv_export_writer('semicolon');

    $csvreviewer->set_filename(get_string('csvreviewer', 'local_workshopcompare'));

    $firstline = array();
    $firstline[] = utf8_decode(get_string('correctorfirstname', 'local_workshopcompare', ""));
    $firstline[] = utf8_decode(get_string('correctorname', 'local_workshopcompare', ""));

    for ($i = 1; $i < 6; $i++) {

        $firstline[] = utf8_decode(get_string('authorfirstname', 'local_workshopcompare', " $i"));
        $firstline[] = utf8_decode(get_string('authorname', 'local_workshopcompare', " $i"));
        $firstline[] = utf8_decode(get_string('authorgrade', 'local_workshopcompare', "$i"));
        $firstline[] = utf8_decode(get_string('authorfinalgrade', 'local_workshopcompare', " $i"));
        $firstline[] = utf8_decode(get_string('authorgap', 'local_workshopcompare', "$i"));
    }

    $firstline[] = utf8_decode(get_string('meangap', 'local_workshopcompare'));

    $csvreviewer->add_data($firstline);

    foreach($reviewers as $reviewer) {

        $newline = array();
        $newline[] = utf8_decode($reviewer->firstname);
        $newline[] = utf8_decode($reviewer->lastname);

        $sql = "SELECT author.firstname, author.lastname, copie.title, correction.grade AS correctgrade,"
                . " copie.grade AS finalgrade FROM mdl_workshop_assessments correction, mdl_workshop_submissions copie,"
                . " mdl_user author WHERE copie.workshopid = $workshop->id AND correction.submissionid = copie.id AND"
                . " copie.authorid = author.id AND correction.reviewerid = $reviewer->id ORDER BY lastname, firstname";
        $reviews = $DB->get_recordset_sql($sql);
        $gaps = array();
        $nbgaps = 0;

        foreach ($reviews as $review) {

            $gaps[$nbgaps] = abs($review->correctgrade - $review->finalgrade);
            $newline[] = utf8_decode($review->firstname);
            $newline[] = utf8_decode($review->lastname);
            $newline[] = utf8_decode($review->correctgrade);
            $newline[] = utf8_decode($review->finalgrade);
            $newline[] = $gaps[$nbgaps];
            $nbgaps++;
        }

        for ($i = $nbgaps; $i <= 4; $i++) {

            $newline[] = "";
            $newline[] = "";
            $newline[] = "";
            $newline[] = "";
            $newline[] = "";
        }

        $averagegap = array_sum($gaps) / $nbgaps;
        $newline[] = "$averagegap";

        $csvreviewer->add_data($newline);
    }

    $csvreviewer->download_file();
} else if ($download == 2) {

    //Liste des auteurs

    $sql = "SELECT DISTINCT author.id, author.firstname, author.lastname, copie.grade FROM mdl_user author,"
            . " mdl_workshop_submissions copie WHERE author.id = copie.authorid AND copie.workshopid = $workshop->id";
    $authors = $DB->get_recordset_sql($sql);

    $csvauthor = new csv_export_writer('semicolon');

    $csvauthor->set_filename(get_string('csvauthor', 'local_workshopcompare'));

    $firstline = array();
    $firstline[] = utf8_decode(get_string('authorfirstname', 'local_workshopcompare', ""));
    $firstline[] = utf8_decode(get_string('authorname', 'local_workshopcompare', ""));
    $firstline[] = utf8_decode(get_string('authorfinalgrade', 'local_workshopcompare', ""));

    for ($i = 1; $i < 6; $i++) {

        $firstline[] = utf8_decode(get_string('correctorfirstname', 'local_workshopcompare', " $i"));
        $firstline[] = utf8_decode(get_string('correctorname', 'local_workshopcompare', " $i"));
        $firstline[] = utf8_decode(get_string('correctorgrade', 'local_workshopcompare', "$i"));
    }

    $csvauthor->add_data($firstline);

    foreach ($authors as $author) {

        $newline = array();
        $newline[] = utf8_decode($author->firstname);
        $newline[] = utf8_decode($author->lastname);
        $newline[] = $author->grade;

        $sql = "SELECT reviewer.firstname, reviewer.lastname, correction.grade "
                . "FROM mdl_user reviewer, mdl_workshop_submissions copie, mdl_workshop_assessments correction "
                . "WHERE copie.workshopid = $workshop->id AND correction.submissionid = copie.id AND"
                . " correction.reviewerid = reviewer.id AND copie.authorid = $author->id";
        $authorreviews = $DB->get_recordset_sql($sql);

        foreach ($authorreviews as $authorreview) {

            $newline[] = utf8_decode($authorreview->firstname);
            $newline[] = utf8_decode($authorreview->lastname);
            $newline[] = $authorreview->grade;
        }

        $csvauthor->add_data($newline);
    }

    $csvauthor->download_file();
}
