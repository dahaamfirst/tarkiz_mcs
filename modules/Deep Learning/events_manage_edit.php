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
use Gibbon\Services\Format;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EventDateGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/events_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $deepLearningEventID = $_GET['deepLearningEventID'] ?? '';

    $page->breadcrumbs
        ->add(__m('Manage Events'), 'events_manage.php')
        ->add(__m('Edit Event'));

    if (empty($deepLearningEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $container->get(EventGateway::class)->getByID($deepLearningEventID);
    $experiences = $container->get(ExperienceGateway::class)->selectExperiencesByEvent($deepLearningEventID)->fetchKeyPair();

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('event', $session->get('absoluteURL').'/modules/'.$session->get('module').'/events_manage_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('deepLearningEventID', $deepLearningEventID);
    $form->addHiddenValue('gibbonSchoolYearID', $values['gibbonSchoolYearID']);

    // DETAILS
    $form->addRow()->addHeading(__('Basic Details'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique for this school year.'));
        $row->addTextField('name')->required()->maxLength(90);

    $row = $form->addRow();
        $row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique for this school year.'));
        $row->addTextField('nameShort')->required()->maxLength(12);

    $row = $form->addRow();
        $row->addLabel('gibbonYearGroupIDList', __('Year Groups'));
        $row->addCheckboxYearGroup('gibbonYearGroupIDList')
            ->addCheckAllNone()
            ->loadFromCSV($values);

    // DATES
    $form->addRow()->addHeading(__('Event Dates'));

    // Determine if these event dates can be deleted, based on existing experiences
    $canDelete = empty($experiences);

    // Custom Block Template
    $addBlockButton = $form->getFactory()->createButton(__('Add Event Date'))->addClass('addBlock');

    $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
    $row = $blockTemplate->addRow()->addClass('w-3/4 flex justify-between mt-1 ml-2');
        $row->addTextField('name')->setClass('w-full mr-1 title')->required()->placeholder(__('Name'));
        $row->addDate('eventDate')->setClass('w-48')->required()->placeholder(__('Date'))
          ->append("<input type='hidden' id='deepLearningEventDateID' name='deepLearningEventDateID' value=''/>");

    // Custom Blocks
    $row = $form->addRow();
    $customBlocks = $row->addCustomBlocks('dates', $session, $canDelete)
        ->fromTemplate($blockTemplate)
        ->settings(['inputNameStrategy' => 'object', 'addOnEvent' => 'click'])
        ->placeholder(__('Event dates will be listed here...'))
        ->addToolInput($addBlockButton);

    $dataBlocks = $container->get(EventDateGateway::class)->selectDates($deepLearningEventID);
    while ($rowBlocks = $dataBlocks->fetch()) {
        $customBlocks->addBlock($rowBlocks['deepLearningEventDateID'], [
            'name' => $rowBlocks['name'],
            'eventDate' => $rowBlocks['eventDate'],
            'deepLearningEventDateID' => $rowBlocks['deepLearningEventDateID'],
        ]);
    }

    // DISPLAY
    $form->addRow()->addHeading(__m('Display'));

    if (!empty($values['viewableDate']) && empty($values['backgroundImage'])) {
        $form->addRow()->addContent(Format::alert(__m('This event is missing a header image and is not viewable in the events list.'), 'warning'));
    }
    $row = $form->addRow();
        $row->addLabel('backgroundImageFile', __m('Header Image'))->description(__m('A header image for the event'));
        $row->addFileUpload('backgroundImageFile')
            ->accepts('.jpg,.jpeg,.gif,.png')
            ->setAttachment('backgroundImage', $session->get('absoluteURL'), $values['backgroundImage']);
    
    $col = $form->addRow()->addColumn();
        $col->addLabel('description', __m('Introduction'));
        $col->addEditor('description', $guid)->showMedia(true);

    // ACCESS
    $form->addRow()->addHeading(__m('Access'));

    $row = $form->addRow();
        $row->addLabel('active', __('Active'))->description(__m('Inactive events are only visible to users with full Manage Events access.'));
        $row->addYesNo('active')->required()->selected('N');

    $row = $form->addRow();
        $row->addLabel('viewableDate', __('Viewable'))->prepend('1. ')->append('<br/>2. '.__m('Experiences are not publicly viewable until this date.'));
        $col = $row->addColumn('viewableDate')->setClass('inline');
        $col->addDate('viewableDate')->setValue(substr($values['viewableDate'] ?? '', 0, 11))->addClass('mr-2');
        $col->addTime('viewableTime')->setValue(substr($values['viewableDate'] ?? '', 11, 5));

    $row = $form->addRow();
        $row->addLabel('accessOpenDate', __('Open Sign-up'))->prepend('1. ')->append('<br/>2. '.__m('Sign-up in unavailable until this date and time.'));
        $col = $row->addColumn('accessOpenDate')->setClass('inline');
        $col->addDate('accessOpenDate')->setValue(substr($values['accessOpenDate'] ?? '', 0, 11))->addClass('mr-2');
        $col->addTime('accessOpenTime')->setValue(substr($values['accessOpenDate'] ?? '', 11, 5));

    $row = $form->addRow();
        $row->addLabel('accessCloseDate', __('Close Sign-up'))->prepend('1. ')->append('<br/>2. '.__m('Sign-up will automatically close on this date.'));
        $col = $row->addColumn('accessCloseDate')->setClass('inline');
        $col->addDate('accessCloseDate')->setValue(substr($values['accessCloseDate'] ?? '', 0, 11))->addClass('mr-2');
        $col->addTime('accessCloseTime')->setValue(substr($values['accessCloseDate'] ?? '', 11, 5));

    $row = $form->addRow();
        $row->addLabel('accessEnrolmentDate', __('Reveal Enrolment'))->prepend('1. ')->append('<br/>2. '.__m('DL groups are hidden from participants until this date.'));
        $col = $row->addColumn('accessEnrolmentDate')->setClass('inline');
        $col->addDate('accessEnrolmentDate')->setValue(substr($values['accessEnrolmentDate'] ?? '', 0, 11))->addClass('mr-2');
        $col->addTime('accessEnrolmentTime')->setValue(substr($values['accessEnrolmentDate'] ?? '', 11, 5));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
