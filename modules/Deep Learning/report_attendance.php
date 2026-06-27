<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\Prefab\ReportTable;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Http\Url;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_attendance.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('Student Attendance by Group'));

    // Setup data
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $viewMode = $_REQUEST['format'] ?? '';

    // Setup gateways
    $eventGateway = $container->get(EventGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));
    
    $params = [
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
    ];

    if (empty($events)) {
        $page->addMessage(__m('There are no active Deep Learning events.'));
        return;
    }
    
    $viewMode = $_REQUEST['format'] ?? '';
    $allAttendance = $_GET['allAttendance'] ?? 'N';
    $currentDate = isset($_GET['currentDate']) ? Format::dateConvert($_GET['currentDate']) : date('Y-m-d');

    // CRITERIA
    $criteria = $enrolmentGateway->newQueryCriteria()
        ->filterBy('event', $params['deepLearningEventID'])
        ->sortBy(['name', 'surname', 'preferredName'])
        ->pageSize(-1)
        ->fromPOST();

    if (empty($viewMode)) {
        $form = Form::create('action', $session->get('absoluteURL') . '/index.php', 'get');

        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Choose Date'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/Deep Learning/report_attendance.php');

        $row = $form->addRow();
            $row->addLabel('deepLearningEventID', __('Event'));
            $row->addSelect('deepLearningEventID')->fromArray($events)->placeholder()->selected($params['deepLearningEventID']);

        $row = $form->addRow();
            $row->addLabel('currentDate', __('Date'));
            $row->addDate('currentDate')->setValue(Format::date($currentDate))->required();

        $row = $form->addRow();
            $row->addLabel('allAttendance', __('Show All?'))->description(__('Include all attendance, event student who are Present.'));
            $row->addCheckbox('allAttendance')->checked($allAttendance)->setValue('Y');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    if (empty($currentDate)) {
        return;
    }
    
    // DATA TABLE
    $attendance = $enrolmentGateway->queryEnrolledStudentsNotPresent($criteria, $params['deepLearningEventID'], $currentDate, $allAttendance);

    $table = ReportTable::createPaginated('attendanceReport', $criteria)->setViewMode($viewMode, $session);
    $table->setTitle(__('Report Data'));

    $table->addMetaData('blankSlate', __('All students are present.'));
    $table->addRowCountColumn($attendance->getPageFrom())->context('primary');

    $table->addColumn('name', __m('Experience'))
        ->description(__m('Trip Leader'))
        ->context('primary')
        ->width('20%')
        ->format(function ($values) {
            $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningExperienceID' => $values['deepLearningExperienceID'], 'sidebar' => 'false']);
            return $values['active'] == 'Y' && $values['viewable'] == 'Y' 
                ? Format::link($url, $values['name'])
                : $values['name'];
        })
        ->formatDetails(function ($values)  {
            return Format::small($values['tripLeaders']);
        });

    $table->addColumn('student', __('Name'))
        ->context('primary')
        ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
        ->format(function ($student) {
            return Format::nameLinked($student['gibbonPersonID'], '', $student['preferredName'], $student['surname'], 'Student', true, true, ['subpage' => 'Attendance']);
        });

    $table->addColumn('formGroup', __('Form Group'))->context('primary')->width('10%');

    $table->addColumn('status', __('Status'))
        ->context('primary')
        ->format(function ($student) {
            return !empty($student['type']) ? __($student['type']) : Format::small(__('Not registered'));
        });
    $table->addColumn('reason', __('Reason'))->context('secondary');
    $table->addColumn('comment', __('Comment'))
        ->format(Format::using('truncate', 'comment'));

    echo $table->render($attendance);
}
