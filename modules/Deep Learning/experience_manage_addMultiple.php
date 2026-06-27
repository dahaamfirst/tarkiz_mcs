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

use Gibbon\Http\Url;
use Gibbon\Forms\Form;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $params = [
        'gibbonSchoolYearID'  => $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'),
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? '',
        'search'              => $_REQUEST['search'] ?? ''
    ];

    $page->breadcrumbs
        ->add(__m('Manage Experiences'), 'experience_manage.php', $params)
        ->add(__m('Add Multiple Experiences'));

    if (!empty($params['search'])) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Deep Learning', 'experience_manage.php')->withQueryParams($params));
    }

    $settingGateway = $container->get(SettingGateway::class);
    $enrolmentMin = $settingGateway->getSettingByScope('Deep Learning', 'enrolmentMin');
    $enrolmentMax = $settingGateway->getSettingByScope('Deep Learning', 'enrolmentMax');

    $events = $container->get(EventGateway::class)->selectAllEvents();
    $units = $container->get(UnitGateway::class)->selectPublishedUnits($params['deepLearningEventID']);

    // FORM
    $form = Form::create('experience', $session->get('absoluteURL').'/modules/'.$session->get('module').'/experience_manage_addMultipleProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $params['gibbonSchoolYearID']);

    // DETAILS
    $form->addRow()->addHeading(__('Basic Details'));

    $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __('Event'))->description(__m('Each experience is part of a Deep Learning event.'));
        $row->addSelect('deepLearningEventID')->fromResults($events, 'groupBy')->required()->placeholder()->selected($params['deepLearningEventID']);

    // STAFF
    $form->addRow()->addHeading(__('Experiences'));

    // Custom Block Template
    $addBlockButton = $form->getFactory()->createButton(__m('Add Experience'))->addClass('addBlock');

    $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
    $row = $blockTemplate->addRow()->addClass('w-3/4 flex justify-start items-center mt-1 ml-2 pr-8');
        $row->addSelect('deepLearningUnitID')->fromResults($units)->setClass('experienceSelect w-full')->required()->placeholder(__m('Unit'));
        $row->addContent()->setClass('w-32');
        $row->addSelectStaff('gibbonPersonID')->photo(true, 'small')->setClass('w-56')->required()->placeholder(__m('Trip Leader'));

    // Custom Blocks
    $col = $form->addRow()->addColumn();
        $col->addContent(Format::alert(__m('Experience fields and settings will be filled in from the unit defaults when the experience is created.'), 'message'));

    $customBlocks = $col->addCustomBlocks('experiences', $session)
        ->fromTemplate($blockTemplate)
        ->settings(['inputNameStrategy' => 'object', 'addOnEvent' => 'click'])
        ->placeholder(__m('Add an Experience...'))
        ->addToolInput($addBlockButton);
        
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
?>

<script>
$(document).on('change', 'select.experienceSelect:not(:disabled)', function () {
    var selectedOptions = [];

    $('select.experienceSelect').each(function() {
        if ($(this).val() != "Unit") {
            selectedOptions.push($(this).val());
        }
    });

    $('select.experienceSelect option:not(:selected)').each(function() {
        if (selectedOptions.includes($(this).val()) && !$(this).html().toString().includes("In Use")) {
            $(this).html($(this).html() + " [In Use]");
        }
    });
});
</script>
