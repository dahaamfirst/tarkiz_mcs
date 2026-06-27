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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Http\Url;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;
use Gibbon\Module\DeepLearning\Domain\EventDateGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceVenueGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $deepLearningExperienceID = $_GET['deepLearningExperienceID'] ?? '';
    $params = [
        'gibbonSchoolYearID' => $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'),
        'search'             => $_REQUEST['search'] ?? ''
    ];

    $page->breadcrumbs
        ->add(__m('Manage Experiences'), 'experience_manage.php', $params)
        ->add(__m('Edit Experience'));

    if (!empty($params['search'])) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Deep Learning', 'experience_manage.php')->withQueryParams($params));
    }

    if (empty($deepLearningExperienceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $experienceGateway = $container->get(ExperienceGateway::class);
    $values = $experienceGateway->getByID($deepLearningExperienceID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $canEditExperience = $experienceGateway->getExperienceEditAccess($deepLearningExperienceID, $session->get('gibbonPersonID'));
    if ($highestAction != 'Manage Experiences_all' && $canEditExperience != 'Y') {
        $page->addError(__m('You do not have edit access to this record.'));
        return;
    }

    if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/unit_manage_edit.php')) {
        $page->navigator->addHeaderAction('edit', __('Edit Unit'))
            ->setURL('/modules/Deep Learning/unit_manage_edit.php')
            ->addParam('deepLearningUnitID', $values['deepLearningUnitID'])
            ->addParams($params)
            ->displayLabel();
    }

    $event = $container->get(EventGateway::class)->getByID($values['deepLearningEventID']);
    $unit = $container->get(UnitGateway::class)->getByID($values['deepLearningUnitID']);

    $form = Form::create('experience', $session->get('absoluteURL').'/modules/'.$session->get('module').'/experience_manage_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('deepLearningEventID', $values['deepLearningEventID']);
    $form->addHiddenValue('deepLearningExperienceID', $deepLearningExperienceID);

    // DETAILS
    $form->addRow()->addHeading(__('Basic Details'));

    $row = $form->addRow();
        $row->addLabel('event', __('Event'));
        $row->addTextField('event')->required()->setValue($event['name'])->readOnly();

    $row = $form->addRow();
        $row->addLabel('unit', __('Unit'));
        $row->addTextField('unit')->required()->setValue($unit['name'])->readOnly();

    $row = $form->addRow();
        $row->addLabel('name', __m('Experience Name'))->description(__m('Must be unique within this Deep Learning event.'));
        $row->addTextField('name')->required()->maxLength(90);

    $row = $form->addRow();
        $row->addLabel('active', __('Active'))->description(__m('Inactive experiences are only visible to users with view permissions.'));
        $row->addYesNo('active')->required()->selected('N');

    $yearGroups = $container->get(EventGateway::class)->selectYearGroupsByEvent($values['deepLearningEventID'])->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('gibbonYearGroupIDList', __('Year Groups'))->description(__m('Defaults to the year groups for the event itself.'));
        $row->addCheckbox('gibbonYearGroupIDList')
            ->fromArray($yearGroups)
            ->addCheckAllNone()
            ->loadFromCSV($values);

    if (!empty($values['gibbonGroupID'])) {
        $url = Url::fromModuleRoute('Messenger', 'groups_manage_edit')->withQueryParams(['gibbonGroupID' => $values['gibbonGroupID']]);
        $row = $form->addRow();
        $row->addLabel('messengerGroup', __m('Messenger Group'))->description(__m('This messenger group was created automatically when the Experience was created.'));
        $row->addContent(Format::link($url, __m('View Group')));
    } else {
        $row = $form->addRow();
            $row->addLabel('createGroup', __m('Create Messenger Group?'));
            $row->addYesNo('createGroup')
                ->selected('N');
    }

    // VENUES
    $form->addRow()->addHeading(__m('Venues'))->append(__m('A venue can be assigned to an experience for each day of the Deep Learning event. These are then used for generating reports as well as helping book rooms for the event.'));

    $eventDates = $container->get(EventDateGateway::class)->selectDates($values['deepLearningEventID'])->fetchAll();

    // Custom Block Template
    $addBlockButton = $form->getFactory()->createButton(__m('Add Venue'))->addClass('addBlock');

    $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
    $row = $blockTemplate->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
    //     $row->addSelectStaff('gibbonPersonID')->photo(false)->setClass('flex-1 mr-1')->required()->placeholder();
    //     $row->addSelect('role')->fromArray($roles)->setClass('w-48 mr-1')->required()->placeholder();
    //     $row->addCheckbox('canEdit')->setLabelClass('w-32')->alignLeft()->setValue('Y')->description(__m('Can Edit?'))
    //         ->append("<input type='hidden' id='deepLearningStaffID' name='deepLearningStaffID' value=''/>");
    // $row = $blockTemplate->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
    //     $row->addTextField('notes')->setClass('w-full')->placeholder(__m('Notes'));

    // Custom Blocks
    $row = $form->addRow();
    $customBlocks = $row->addCustomBlocks('venues', $session)
        ->fromTemplate($blockTemplate)
        ->settings(array('inputNameStrategy' => 'object', 'addOnEvent' => 'click'))
        ->placeholder(__m('Add a Venue...'))
        ->addToolInput($addBlockButton);

    $venues = $container->get(ExperienceVenueGateway::class)->selectVenuesByExperience($deepLearningExperienceID);
    while ($venue = $venues->fetch()) {
        $customBlocks->addBlock($venue['deepLearningExperienceVenueID'], [
            'deepLearningExperienceID' => $venue['deepLearningExperienceID'],
            'deepLearningEventDateID'  => $venue['deepLearningEventDateID'],
            'gibbonSpaceID'            => $venue['gibbonSpaceID'],
            'venueExternal'            => $venue['venueExternal'],
            'venueExternalUrl'         => $venue['venueExternalUrl'],
            'description'              => $venue['description'],
            'allDay'                   => $venue['allDay'],
            'timeStart'                => $venue['timeStart'],
            'timeEnd'                  => $venue['timeEnd'],

        ]);
    }

    // foreach ($eventDates as $eventDate) {
    //     $index = $eventDate['deepLearningEventDateID'];

    //     $form->toggleVisibilityByClass("venueInternal{$index}")->onRadio("venue[{$index}][type]")->when('Internal');
    //     $form->toggleVisibilityByClass("venueExternal{$index}")->onRadio("venue[{$index}][type]")->when('External');

    //     $row = $form->addRow();
    //         $row->addLabel("venue[{$index}][type]", $eventDate['name']);
    //         $col = $row->addColumn()->setClass('flex flex-col');
    //             $col->addRadio("venue[{$index}][type]")
    //                 ->inline()
    //                 ->alignLeft()
    //                 ->fromArray([
    //                     'Internal' => __('Internal'),
    //                     'External' => __('External')
    //                 ]);

    //             $col->addSelectSpace("venue[{$index}]['gibbonSpaceID']")
    //                 ->addClass("venueInternal{$index}")
    //                 ->placeholder()
    //                 ->addClass('sm:max-w-full w-full');

    //             $col->addTextField("venue[{$index}]['external']")
    //                 ->addClass("venueExternal{$index}")
    //                 ->maxLength(50)
    //                 ->addClass('sm:max-w-full w-full');

    // }

    // STAFF
    $form->addRow()->addHeading(__('Staff'))->append(__m('Staff can be added here, as well as through the Manage DL Staffing page. Staff added to an experience are listed in the Deep Learning Overview, and can be given access to edit the experience details.'));

    // Custom Block Template
    $addBlockButton = $form->getFactory()->createButton(__m('Add Staff'))->addClass('addBlock');

    $roles = ['Trip Leader' => __m('Trip Leader'), 'Teacher' => __('Teacher'), 'Support' => __('Support')];
    $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
    $row = $blockTemplate->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
        $row->addSelectStaff('gibbonPersonID')->photo(false)->setClass('flex-1 mr-1')->required()->placeholder();
        $row->addSelect('role')->fromArray($roles)->setClass('w-48 mr-1')->required()->placeholder();
        $row->addCheckbox('canEdit')->setLabelClass('w-32')->alignLeft()->setValue('Y')->description(__m('Can Edit?'))
            ->append("<input type='hidden' id='deepLearningStaffID' name='deepLearningStaffID' value=''/>");
    $row = $blockTemplate->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
        $row->addTextField('notes')->setClass('w-full')->placeholder(__m('Notes'));

    // Custom Blocks
    $row = $form->addRow();
    $customBlocks = $row->addCustomBlocks('staff', $session)
        ->fromTemplate($blockTemplate)
        ->settings(array('inputNameStrategy' => 'object', 'addOnEvent' => 'click'))
        ->placeholder(__m('Add a Trip Leader...'))
        ->addToolInput($addBlockButton);

    $staff = $container->get(StaffGateway::class)->selectStaffByExperience($deepLearningExperienceID);
    while ($person = $staff->fetch()) {
        $customBlocks->addBlock($person['deepLearningStaffID'], [
            'deepLearningStaffID' => $person['deepLearningStaffID'],
            'gibbonPersonID'      => $person['gibbonPersonID'],
            'role'                => $person['role'] ?? 'Support',
            'canEdit'             => $person['canEdit'] ?? 'N',
            'notes'               => $person['notes'] ?? '',
        ]);
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();

    // Cancel out if Trip Planner not installed or not accessible
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    $tripPlanner = $experienceTripGateway->getTripPlannerModule();

    $highestTripAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if (empty($tripPlanner) || empty($highestTripAction)) {
        return;
    }

    // TRIPS
    $gibbonPersonID = $session->get('gibbonPersonID');
    $criteria = $experienceTripGateway->newQueryCriteria()
        ->sortBy(['title'])
        ->pageSize(-1)
        ->fromPOST();

    $trips = $experienceTripGateway->queryTripsByExperience($criteria, $deepLearningExperienceID);

    $table = DataTable::create('trips');
    $table->setTitle(__m('Trip Planning'));
    $table->setDescription(__m('Trips can be attached to Deep Learning experiences, simplifying the setup of new trips. Add and edit trips from the list below to automatically attached them to this experience.'));

    $table->modifyRows(function (&$trip, $row) {
        if ($trip['status'] == 'Approved') $row->addClass('success');
        if ($trip['status'] == 'Draft') $row->addClass('dull');
        if ($trip['status'] == 'Awaiting Final Approval') $row->addClass('message');
        if ($trip['status'] == 'Rejected' || $trip['status'] == 'Cancelled') $row->addClass('dull');

        return $row;
    });
    
    $table->addHeaderAction('addTrip', __m('Add Trip Request'))
        ->setURL('/modules/Deep Learning/experience_manage_edit_addTrip.php')
        ->setIcon('page_new')
        ->addParam('deepLearningExperienceID', $deepLearningExperienceID)
        ->displayLabel()
        ->modalWindow(650, 460);
    
    $table->addExpandableColumn('contents')
        ->format(function ($trip) {
            return $trip['description'];
        });

    $table->addColumn('tripTitle', __('Title'))
        ->format(function ($trip) {
            return $trip['tripTitle'].($trip['status'] == 'Draft' ? Format::tag(__('Draft'), 'message ml-2') : '');
        });

    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');

    $table->addColumn('firstDayOfTrip', __('First Day of Trip'))
        ->format(Format::using('dateReadable', ['firstDayOfTrip']));

    $table->addColumn('status', __('Status'))->format(function($trip) {
        return $trip['status'];
    });

    $table->addActionColumn()
        ->addParam('tripPlannerRequestID')
        ->addParam('deepLearningExperienceID', $deepLearningExperienceID)
        ->format(function ($trip, $actions) use ($highestTripAction, $gibbonPersonID)  {
            $actions->addAction('view', __('View Details'))
                ->setURL('/modules/Trip Planner/trips_requestView.php');

            if (($highestTripAction == 'Manage Trips_full' || $gibbonPersonID == $trip['creatorPersonID']) && !in_array($trip['status'], ['Cancelled', 'Rejected'])) {
                $actions->addAction('edit', __('Edit'))
                    ->addParam('mode', 'edit')
                    ->setURL('/modules/Trip Planner/trips_submitRequest.php');
            }
    });
        
    echo $table->render($trips);
}
