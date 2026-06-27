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
use Gibbon\Tables\DataTable;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Manage Enrolment by Person'));

    $params = [
        'search' => $_REQUEST['search'] ?? ''
    ];

    // CRITERIA
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $criteria = $enrolmentGateway->newQueryCriteria(true)
        ->searchBy($enrolmentGateway->getSearchableColumns(), $params['search'])
        ->sortBy(['surname', 'preferredName'])
        ->fromPOST();

    // SEARCH
    $form = Form::create('filters', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/Deep Learning/enrolment_manage_byPerson.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search For'))->description(__m('Preferred name, surname, experience name, event name'));
        $row->addTextField('search')->setValue($criteria->getSearchText())->maxLength(20);

    $row = $form->addRow();
        $row->addSearchSubmit($session, 'Clear Filters', ['view', 'sidebar']);

    echo $form->getOutput();

    $enrolment = $enrolmentGateway->queryEnrolment($criteria, $session->get('gibbonSchoolYearID'));

    // TABLE
    $table = DataTable::createPaginated('enrolment', $criteria);

    $table->modifyRows(function($values, $row) {
        if ($values['status'] == 'Pending') $row->addClass('warning');
        return $row;
    });
    
    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php')
        ->addParam('mode', 'add')
        ->addParam('search', $criteria->getSearchText(true))
        ->displayLabel();

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

    $table->addColumn('formGroup', __('Form Group'))
        ->width('6%')
        ->context('secondary');

    $table->addColumn('eventNameShort', __m('Event'))
        ->width('6%');
        
    $table->addColumn('name', __m('Experience'))
        ->context('primary')
        ->width('25%');

    $choices = ['1' => __m('1st'), '2' => __m('2nd'), '3' => __m('3rd'), '4' => __m('4th'), '5' => __m('5th')];
    $table->addColumn('status', __('Status'))
        ->description(__m('Choice'))
        ->context('secondary')
        ->width('15%')
        ->formatDetails(function ($values) use ($choices) {
            return Format::small($choices[$values['choice']] ?? $values['choice']);
        });

    $table->addColumn('timestampCreated', __('When'))
        ->format(Format::using('dateReadable', 'timestampCreated'))
        ->width('15%');


    // ACTIONS
    $table->addActionColumn()
        ->addParam('search', $criteria->getSearchText(true))
        ->addParam('deepLearningEnrolmentID')
        ->format(function ($values, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php')
                    ->addParam('mode', 'edit');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_delete.php');
        });

    echo $table->render($enrolment);
}
