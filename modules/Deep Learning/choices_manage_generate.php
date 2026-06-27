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
use Gibbon\Forms\MultiPartForm;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Module\DeepLearning\EnrolmentGenerator;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/choices_manage_generate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $params = [
        'step' => $_REQUEST['step'] ?? 1,
        'sidebar' => 'false',
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? '',
    ];

    $page->breadcrumbs
        ->add(__m('Manage Choices'), 'choices_manage.php')
        ->add(__m('Generate DL Groups'));
     
    $page->return->addReturns([
        'error4' => __m(''),
    ]);

    $step = $_REQUEST['step'] ?? 1;

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $choiceGateway = $container->get(ChoiceGateway::class);
    
    $signUpChoices = $container->get(SettingGateway::class)->getSettingByScope('Deep Learning', 'signUpChoices');
    $choiceList = [1 => __m('First Choice'), 2 => __m('Second Choice'), 3 => __m('Third Choice'), 4 => __m('Fourth Choice'), 5 => __m('Fifth Choice')];

    $pageUrl = Url::fromModuleRoute('Deep Learning', 'choices_manage_generate.php')->withQueryParams($params);
    
    // FORM
    $form = MultiPartForm::create('generate', (string)$pageUrl);
   
    $form->setCurrentPage($params['step']);
    $form->addPage(1, __m('Select an Event'), $pageUrl);
    $form->addPage(2, __m('Confirm Experiences'), $pageUrl);
    $form->addPage(3, __m('Create Groups'), $pageUrl);
    $form->addPage(4, __m('View Results'), $pageUrl);

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('step', $params['step'] + 1);

    if ($form->getCurrentPage() == 1) {
        // STEP 1
        $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();

        $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __('Event'));
        $row->addSelect('deepLearningEventID')
            ->fromArray($events)
            ->required()
            ->placeholder()
            ->selected($params['deepLearningEventID']);

        $form->addRow()->addSubmit(_('Next'));

    } elseif ($form->getCurrentPage() == 2) {
        // STEP 2
        if (empty($params['deepLearningEventID'])) {
            $page->addError(__('You have not specified one or more required parameters.'));
            return;
        }

        $experiences = $experienceGateway->selectExperienceDetailsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();
        $choiceCounts = $choiceGateway->selectChoiceCountsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();

        $table = $form->addRow()->addTable()->setClass('mini w-full');
        $table->addClass('bulkActionForm colorOddEven');

        $header = $table->addHeaderRow();
            $header->addTableCell(__m('Include'));
            $header->addTableCell(__m('Experience'));
            $header->addTableCell(__('Minimum Enrolment'));
            $header->addTableCell(__('Maximum Enrolment'));
            for ($i = 1; $i <= $signUpChoices; $i++) {
                $header->addTableCell($choiceList[$i]);
            }
        
        $totalMin = $totalMax = $totalChoice = 0;

        foreach ($experiences as $experience) {
            $index = $experience['deepLearningExperienceID'];
            $totalMin += $experience['enrolmentMin'] ?? 0;
            $totalMax += $experience['enrolmentMax'] ?? 0;
            $totalChoice += $choiceCounts[$index]['choice1'] ?? 0;

            $row = $table->addRow();
            $row->addCheckbox("experience[{$index}][generate]")
                ->setClass('w-12 bulkCheckbox')
                ->alignCenter()
                ->setValue('Y')
                ->checked('Y');
            $row->addLabel("experience{$index}", $experience['name']);
            $row->addNumber("experience[{$index}][enrolmentMin]")
                ->setClass('w-24')
                ->onlyInteger(true)
                ->minimum(0)
                ->maximum(999)
                ->maxLength(3)
                ->required()
                ->setValue($experience['enrolmentMin']);
            $row->addNumber("experience[{$index}][enrolmentMax]")
                ->setClass('w-24')
                ->onlyInteger(true)
                ->minimum(0)
                ->maximum(999)
                ->maxLength(3)
                ->required()
                ->setValue($experience['enrolmentMax']);

                for ($i = 1; $i <= $signUpChoices; $i++) {
                $row->addTextField("experience[{$index}][choice{$i}]")
                    ->setClass('w-24 text-black opacity-100')
                    ->readOnly()
                    ->disabled()
                    ->setValue($choiceCounts[$index]["choice{$i}"] ?? '');
            }
        }

        $row = $table->addRow();
            $row->addTableCell();
            $row->addTableCell(__m('{count} Experience(s)', ['count' => count($experiences)]));  
            $row->addTableCell(__m('Min').': '.$totalMin)->setClass('text-center');  
            $row->addTableCell(__m('Max').': '.$totalMax)->setClass('text-center');  
            $row->addTableCell(__m('{count} Sign-up(s)', ['count' => $totalChoice]))->setClass('text-center');  

        if ($totalChoice > $totalMax) {
            $form->addRow()->addContent(Format::alert(__m('The maximum available spaces is {max}, which is less than the total sign ups. {difference} participants will not be automatically added to groups, but can still be added manually.', ['max' => $totalMax, 'difference' => $totalChoice - $totalMax]), 'warning'));
        }
        $form->addRow()->addSubmit(_('Next'));

    } elseif ($form->getCurrentPage() == 3) {
        // STEP 3
        $form->setClass('blank w-full');
        $form->setAction((string)$pageUrl->withQueryParam('sidebar', 'true'));

        // Collect only the experiences that were submitted for generation
        $experienceList = array_filter($_POST['experience'] ?? [], function($item) {
            return !empty($item['generate']) && $item['generate'] == 'Y';
        });

        // Use the generator class to handle turning choices into groups for each experience
        $generator = $container->get(EnrolmentGenerator::class);

        $generator
            ->loadExperiences($params['deepLearningEventID'], $experienceList)
            ->loadEnrolments($params['deepLearningEventID'])
            ->loadChoices($params['deepLearningEventID'])
            ->generateGroups();
    
        // Display the drag-drop group editor
        $form->addRow()->addContent($page->fetchFromTemplate('generate.twig.html', [
            'signUpChoices' => $signUpChoices,
            'experiences' => $generator->getExperiences(),
            'groups'      => $generator->getGroups(),
            'mode' => 'student',
        ]));

        $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
        $row = $table->addRow()->addSubmit(__('Submit'));
    } elseif ($form->getCurrentPage() >= 3) {
        // STEP 4
        $enrolmentList = $_POST['person'] ?? [];
        
        $generator = $container->get(EnrolmentGenerator::class);
        $results = $generator->createEnrolments($params['deepLearningEventID'], $enrolmentList, $session->get('gibbonPersonID'));

        $form->addRow()->addContent(Format::alert(__m('Enrolment generation has completed successfully, creating {total} enrolments with {unassigned} left unassigned.', $results), 'success'));

        if (!empty($results['error'])) {
            $form->addRow()->addContent(Format::alert(__m('There was an error creating {error} enrolments, which likely already have duplicate enrolments for this Deep Learning events.', $results), 'error'));
        }

        $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full max-w-lg mx-auto');

        $row = $table->addRow();
            $row->addLabel('total', __m('Total Enrolments'));
            $row->addTextField('totalValue')->setClass('w-24')->readonly()->setValue($results['total'] ?? 0);
            $row->addContent();

        for ($i = 1; $i <= $signUpChoices; $i++) {
            $row = $table->addRow();
            $row->addLabel("choice{$i}", $choiceList[$i]);
            $row->addTextField("choice{$i}Value")->setClass('w-24')->readonly()->setValue( ($results["choice{$i}"] ?? 0) );
            $row->addContent( round( ($results["choice{$i}"]/$results['total'])*100).'%');
        }

        $row = $table->addRow();
            $row->addLabel('unassigned', __m('Unassigned'));
            $row->addTextField('unassignedValue')->setClass('w-24')->readonly()->setValue( ($results['unassigned'] ?? 0) );
            $row->addContent( round( ($results['unassigned']/$results['total'])*100).'%');
    }

    echo $form->getOutput();
}
