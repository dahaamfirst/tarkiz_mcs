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
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_notEnrolled.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('Students Not Enrolled'));

    // Setup data
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $viewMode = $_REQUEST['format'] ?? '';

    // Setup gateways
    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));
    
    $params = [
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
        'search'             => $_REQUEST['search'] ?? ''
    ];


    if (empty($events)) {
        $page->addMessage(__m('There are no active Deep Learning events.'));
        return;
    }
    
    // CRITERIA
    $criteria = $enrolmentGateway->newQueryCriteria(true)
        ->searchBy($enrolmentGateway->getSearchableColumns(), $params['search'])
        ->sortBy(['yearGroupSequence', 'formGroup', 'surname', 'preferredName'])
        ->filterBy('event', $params['deepLearningEventID'])
        ->pageSize(-1)
        ->fromPOST();

    if (empty($viewMode)) {
        // FILTER
        $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');

        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_notEnrolled.php');
        $form->addHiddenValue('address', $session->get('address'));

        $row = $form->addRow();
            $row->addLabel('deepLearningEventID', __('Event'));
            $row->addSelect('deepLearningEventID')->fromArray($events)->placeholder()->selected($params['deepLearningEventID']);

        $row = $form->addRow();
            $row->addLabel('search', __('Search For'))->description(__m('Preferred name, surname'));
            $row->addTextField('search')->setValue($criteria->getSearchText())->maxLength(20);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    $unenrolled = $enrolmentGateway->queryUnenrolledStudentsByEvent($criteria, $params['deepLearningEventID']);
    
    // DATA TABLE
    $table = ReportTable::createPaginated('report_notEnrolled', $criteria)->setViewMode($viewMode, $session);

    $table->setTitle(__m('Students Not Enrolled'));

    $table->addColumn('image_240', __('Photo'))
        ->context('primary')
        ->width('8%')
        ->notSortable()
        ->format(Format::using('userPhoto', ['image_240', 'xs']));

    $table->addColumn('student', __('Person'))
        ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
        ->width('25%')
        ->format(function ($values) {
            return Format::nameLinked($values['gibbonPersonID'], '', $values['preferredName'], $values['surname'], 'Student', true, true);
        });

    $table->addColumn('formGroup', __('Form Group'))->context('secondary');

    $table->addColumn('email', __('Email'));

    $table->addColumn('choices', __('Choices'))
        ->context('primary')
        ->width('30%')
        ->format(function ($values) {
            if (empty($values['choices'])) return '';
            $choices = explode(',', $values['choices']);
            return Format::list($choices, 'ol', 'ml-2 my-0 text-xs');
        });
        
    $table->addColumn('eventNameShort', __('Event'))
        ->width('8%');

    

    echo $table->render($unenrolled);
}
