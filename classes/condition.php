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
 * Activity other completion condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');

class condition extends \core_availability\condition {
    /** @var int ID of module that this depends on */
    protected $cmid;

    /** @var int Expected completion type (one of the COMPLETE_xx constants) */
    protected $expectedcompletion;

    /** @var array Array of modules used in these conditions for course */
    protected static $modsusedincondition = array();

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get cmid.
        if (isset($structure->cm) && is_number($structure->cm)) {
            $this->cmid = (int)$structure->cm;
        } else {
            throw new \coding_exception('Missing or invalid ->cm for completion condition');
        }

        // Get expected completion.
        if (isset($structure->e) && in_array($structure->e,
                array(COMPLETION_COMPLETE, COMPLETION_INCOMPLETE))) {
            $this->expectedcompletion = $structure->e;
        } else {
            throw new \coding_exception('Missing or invalid ->e for completion condition');
        }
    }

    public function save() {
        return (object)array('type' => 'othercompleted',
                'cm' => $this->cmid, 'e' => $this->expectedcompletion);
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param int $cmid Course id of other activity
     * @param int $expectedcompletion Expected completion value (COMPLETION_xx)
     */
    public static function get_json($cmid, $expectedcompletion) {
        return (object)array('type' => 'othercompleted', 'cm' => (int)$cmid,
                'e' => (int)$expectedcompletion);
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        //get course completion details to allow preview

        global $DB;

        $course = $this->cmid;
        $user = $DB->get_record('course_completions', array('userid'=> $userid, 'course'=> $course));

        //if data is available means user has been completed course
        if($user->id > 0 && $user->timecompleted != NULL){

            $allow = true;
        }
        else{
            $allow = false;
        }

        return $allow;
    }

    /**
     * Returns a more readable keyword corresponding to a completion state.
     *
     * Used to make lang strings easier to read.
     *
     * @param int $completionstate COMPLETION_xx constant
     * @return string Readable keyword
     */
    protected static function get_lang_string_keyword($completionstate) {
        switch($completionstate) {
            case COMPLETION_INCOMPLETE:
                return 'incomplete';
            case COMPLETION_COMPLETE:
                return 'complete';
            default:
                throw new \coding_exception('Unexpected completion state: ' . $completionstate);
        }
    }

    //get details restrict access
    public function get_description($full, $not, \core_availability\info $info) {
        // Get name for module.
        $modname = $DB->get_record('course', ['id' => $this->cmid])->fullname;

        // Work out which lang string to use.
        if ($not) {
            // Convert NOT strings to use the equivalent where possible.
            switch ($this->expectedcompletion) {
                case COMPLETION_INCOMPLETE:
                    $str = 'requires_' . self::get_lang_string_keyword(COMPLETION_COMPLETE);
                    break;
                case COMPLETION_COMPLETE:
                    $str = 'requires_' . self::get_lang_string_keyword(COMPLETION_INCOMPLETE);
                    break;
                default:
                    // The other two cases do not have direct opposites.
                    $str = 'requires_not_' . self::get_lang_string_keyword($this->expectedcompletion);
                    break;
            }
        } else {
            $str = 'requires_' . self::get_lang_string_keyword($this->expectedcompletion);
        }

        return get_string($str, 'availability_othercompleted', $modname);
    }

    protected function get_debug_string() {
        switch ($this->expectedcompletion) {
            case COMPLETION_COMPLETE :
                $type = 'COMPLETE';
                break;
            case COMPLETION_INCOMPLETE :
                $type = 'INCOMPLETE';
                break;
            default:
                throw new \coding_exception('Unexpected expected completion');
        }
        return 'cm' . $this->cmid . ' ' . $type;
    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        global $DB;
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'course_module', $this->cmid);
        if (!$rec || !$rec->newitemid) {
            // If we are on the same course (e.g. duplicate) then we can just
            // use the existing one.
            if ($DB->record_exists('course_modules',
                    array('id' => $this->cmid, 'course' => $courseid))) {
                return false;
            }
            // Otherwise it's a warning.
            $this->cmid = 0;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on module that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->cmid = (int)$rec->newitemid;
        }
        return true;
    }

    /**
     * Used in course/lib.php because we need to disable the completion JS if
     * a completion value affects a conditional activity.
     *
     * @param \stdClass $course Moodle course object
     * @param int $cmid Course id
     * @return bool True if this is used in a condition, false otherwise
     */
    public static function completion_value_used($course, $cmid) {
        // Have we already worked out a list of required completion values
        // for this course? If so just use that.
        if (!array_key_exists($course->id, self::$modsusedincondition)) {
            // We don't have data for this course, build it.
            $modinfo = get_fast_modinfo($course);
            self::$modsusedincondition[$course->id] = array();

            // Activities.
            foreach ($modinfo->datcm as $othercm) {
                if (is_null($othercm->availability)) {
                    continue;
                }
                $ci = new \core_availability\info_module($othercm);
                $tree = $ci->get_availability_tree();
                foreach ($tree->get_all_children('availability_othercompleted\condition') as $cond) {
                    self::$modsusedincondition[$course->id][$cond->cmid] = true;
                }
            }

            // Sections.
            foreach ($modinfo->get_section_info_all() as $section) {
                if (is_null($section->availability)) {
                    continue;
                }
                $ci = new \core_availability\info_section($section);
                $tree = $ci->get_availability_tree();
                foreach ($tree->get_all_children('availability_othercompleted\condition') as $cond) {
                    self::$modsusedincondition[$course->id][$cond->cmid] = true;
                }
            }
        }
        return array_key_exists($cmid, self::$modsusedincondition[$course->id]);
    }

    /**
     * Wipes the static cache of modules used in a condition (for unit testing).
     */
    public static function wipe_static_cache() {
        self::$modsusedincondition = array();
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'course_modules' && (int)$this->cmid === (int)$oldid) {
            $this->cmid = $newid;
            return true;
        } else {
            return false;
        }
    }
}
