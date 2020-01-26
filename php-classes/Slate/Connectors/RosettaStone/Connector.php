<?php

namespace Slate\Connectors\RosettaStone;

use Site;
use DB;
use SpreadsheetWriter;

use ActiveRecord;
use Emergence\Connectors\Mapping;

use Slate\Term;
use Slate\Courses\Course;
use Slate\People\Student;


class Connector extends \Emergence\Connectors\AbstractConnector
{
    public static $rosettaDomain;
    public static $sections = [];
    public static $group;
    public static $language;
    public static $curriculum;

    public static $connectorId = 'rosetta-stone';

    public static function handleRequest($action = null)
    {
        switch ($action ?: $action = static::shiftPath()) {
            case 'students.csv':
                return static::handleStudentsRequest();
            case 'launch':
                return static::handleLaunchRequest();
            default:
                return parent::handleRequest($action);
        }
    }

    public static function handleStudentsRequest()
    {
        $GLOBALS['Session']->requireAccountLevel('Administrator');

        // get term
        if (!empty($_REQUEST['term'])) {
            if (!$Term = Term::getByHandle($_REQUEST['term'])) {
                return static::throwInvalidRequestError('term not found');
            }
        } else {
            $Term = Term::getClosest()->getMaster();
        }

        // init spreadsheet writer
        $spreadsheet = new SpreadsheetWriter();

        // write header
        $spreadsheet->writeRow([
            'Username',
            'Password',
            'Group',
            'Language',
            'Curriculum',
            'First Name',
            'Middle Name',
            'Last Name',
            'Notes',
            'E-mail'
        ]);

        if (count(static::$sections)) {
            // retrieve results
            $result = DB::query(
                'SELECT participant.PersonID, section.Code'
                .' FROM course_sections section'
                .' JOIN courses course ON course.ID = section.CourseID'
                .' RIGHT JOIN course_section_participants participant ON participant.CourseSectionID = section.ID'
                .' WHERE section.TermID IN (%s) AND course.Code IN ("%s") AND participant.Role = "Student"'
                .' ORDER BY section.Code',
                [
                    implode(',', $Term->getRelatedTermIDs()),
                    implode('","', static::$sections)
                ]
            );

            // output results
            while ($row = $result->fetch_assoc()) {
                $Student = Student::getById($row['PersonID']);

                $mappingData = [
                    'ContextClass' => $Student->getRootClass(),
                    'ContextID' => $Student->ID,
                    'Connector' => static::getConnectorId(),
                    'ExternalKey' => 'learner[password]'
                ];

                if (!$Mapping = Mapping::getByWhere($mappingData)) {
                    $mappingData['ExternalIdentifier'] = Student::generatePassword();
                    $Mapping = Mapping::create($mappingData, true);
                }

                // write row
                $spreadsheet->writeRow([
                    $Student->Username,
                    $Mapping->ExternalIdentifier,
                    static::$group,
                    static::$language,
                    static::$curriculum,
                    $Student->FirstName,
                    $Student->MiddleName,
                    $Student->LastName,
                    $row['Code'],
                    $Student->PrimaryEmail->Data
                ]);
            }
        }

        $spreadsheet->close();
    }

    public static function handleLaunchRequest()
    {
        $GLOBALS['Session']->requireAuthentication();

        $Mapping = Mapping::getByWhere([
            'ContextClass' => Student::getStaticRootClass(),
            'ContextID' => $GLOBALS['Session']->PersonID,
            'Connector' => static::getConnectorId(),
            'ExternalKey' => 'learner[password]'
        ]);

        if (!$Mapping) {
            Site::redirect('https://'.static::$rosettaDomain.'/en-US/portal');
        }

        print('<form id="rosettaLoginForm" action="https://'.static::$rosettaDomain.'/en-US/portal/login" method="POST">');
        print('<input type="hidden" name="login_user[user_name]" value="'.htmlspecialchars($Mapping->Context->Username).'">');
        print('<input type="hidden" name="login_user[password]" value="'.htmlspecialchars($Mapping->ExternalIdentifier).'">');
        print('</form>');
        print('<script>document.getElementById("rosettaLoginForm").submit()</script>');
    }
}