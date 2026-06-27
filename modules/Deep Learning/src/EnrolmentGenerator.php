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

namespace Gibbon\Module\DeepLearning;

use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * Facilitates turning student choices into a set of potential enrolment groups 
 * for each of the selected experiences.
 */
class EnrolmentGenerator 
{
    protected $experienceGateway;
    protected $enrolmentGateway;
    protected $choiceGateway;

    protected $signUpChoices;

    protected $experiences;
    protected $enrolments;
    protected $choices;
    protected $groups;

    public function __construct(ExperienceGateway $experienceGateway, EnrolmentGateway $enrolmentGateway, ChoiceGateway $choiceGateway, SettingGateway $settingGateway)
    {
        $this->experienceGateway = $experienceGateway;
        $this->enrolmentGateway = $enrolmentGateway;
        $this->choiceGateway = $choiceGateway;

        $this->signUpChoices = $settingGateway->getSettingByScope('Deep Learning', 'signUpChoices');
    }

    public function getExperiences()
    {
        return $this->experiences;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function loadExperiences(string $deepLearningEventID, array $experienceList)
    {
        // Filter details to only those checked for this generation process
        $this->experiences = $this->experienceGateway->selectExperienceDetailsByEvent($deepLearningEventID)->fetchGroupedUnique();
        $this->experiences = array_intersect_key($this->experiences, $experienceList);

        // Update min and max values for the selected experiences
        foreach ($this->experiences as $deepLearningExperienceID => $experience) {
            $experience['enrolmentMin'] = $experienceList[$deepLearningExperienceID]['enrolmentMin'] ?? $experience['enrolmentMin'];
            $experience['enrolmentMax'] = $experienceList[$deepLearningExperienceID]['enrolmentMax'] ?? $experience['enrolmentMax'];

            $this->experienceGateway->update($deepLearningExperienceID, [
                'enrolmentMin' => $experience['enrolmentMin'],
                'enrolmentMax' => $experience['enrolmentMax'],
            ]);

            $this->experiences[$deepLearningExperienceID] = $experience;
        }

        return $this;
    }

    public function loadEnrolments(string $deepLearningEventID)
    {
        $this->enrolments = $this->enrolmentGateway->selectEnrolmentsByEvent($deepLearningEventID)->fetchGroupedUnique();

        return $this;
    }

    public function loadChoices(string $deepLearningEventID)
    {
        $this->choices = $this->choiceGateway->selectChoicesByEvent($deepLearningEventID)->fetchGroupedUnique();

        foreach ($this->choices as $gibbonPersonID => $person) {
            if (!empty($this->enrolments[$gibbonPersonID])) continue;
            
            for ($i = 1; $i <= $this->signUpChoices; $i++) {
                $person["choice{$i}"] = str_pad($person["choice{$i}"], 12, '0', STR_PAD_LEFT);
                $person["choice{$i}Name"] = $this->experiences[$person["choice{$i}"]]['name'] ?? '';
            }

            $this->choices[$gibbonPersonID] = $person;
        }

        $this->sortChoicesByWeighting($deepLearningEventID);

        return $this;
    }

    public function generateGroups()
    {
        // Preload any existing enrolments
        foreach ($this->enrolments as $gibbonPersonID => $person) {
            $person['enrolled'] = true;
            $this->groups[$person['deepLearningExperienceID']][$gibbonPersonID] = $person;
        }

        // Assign choices to groups until the groups fill up
        foreach ($this->choices as $gibbonPersonID => $person) {
            $enrolmentGroup = 0;

            for ($i = 1; $i <= 3; $i++) {
                if (empty($person["choice{$i}"])) continue;

                $choiceExperience = $this->experiences[$person["choice{$i}"]] ?? ['enrolmentMax' => 0];
                $groupCount = count($this->groups[$person["choice{$i}"]] ?? []);

                if ($groupCount < $choiceExperience['enrolmentMax']) {
                    $enrolmentGroup = $person["choice{$i}"];
                    break;
                }
            }

            $this->groups[$enrolmentGroup][$gibbonPersonID] = $person;
        }

        // Sort each resulting group alphabetically
        foreach ($this->groups as $enrolmentGroup => $group) {
            uasort($group, function ($a, $b) {
                if ($a['surname'] != $b['surname']) {
                    return $a['surname'] <=> $b['surname'];
                }

                return $a['preferredName'] <=> $b['preferredName'];
            });

            $this->groups[$enrolmentGroup] = $group;
        }

        return $this;
    }

    public function createEnrolments($deepLearningEventID, $enrolmentList, $gibbonPersonIDCreated = null) : array
    {
        $results = ['total' => 0, 'choice0' => 0, 'choice1' => 0, 'choice2' => 0, 'choice3' => 0, 'choice4' => 0, 'choice5' => 0, 'unassigned' => 0, 'inserted' => 0, 'updated' => 0, 'error' => 0];

        foreach ($enrolmentList as $gibbonPersonID => $deepLearningExperienceID) {
            if (empty($deepLearningExperienceID)) {
                $results['unassigned']++;
                continue;
            }

            // Connect the choice to the enrolment, for future queries and weighting
            $choice = $this->choiceGateway->getChoiceByExperienceAndPerson($deepLearningExperienceID, $gibbonPersonID);
            $choiceNumber = intval($choice['choice'] ?? 0);

            $selectBy = [
                'deepLearningEventID' => $deepLearningEventID,
                'gibbonPersonID'      => $gibbonPersonID,
            ];
            $enrolment = $this->enrolmentGateway->selectBy($selectBy)->fetch();

            if (!empty($enrolment)) {
                // Update and existing enrolment
                $data = [
                    'deepLearningExperienceID' => $deepLearningExperienceID,
                    'deepLearningChoiceID'     => $choice['deepLearningChoiceID'] ?? null,
                ];
    
                $updated = $this->enrolmentGateway->updateWhere($selectBy, $data);
                $results['total']++;
                $results['updated']++;
                $results["choice".$choiceNumber]++;
                
            } else {
                // Add a new enrolment
                $data = [
                    'deepLearningExperienceID' => $deepLearningExperienceID,
                    'deepLearningEventID'      => $deepLearningEventID,
                    'deepLearningChoiceID'     => $choice['deepLearningChoiceID'] ?? null,
                    'gibbonPersonID'           => $gibbonPersonID,
                    'status'                   => 'Confirmed',
                    'notes'                    => '',
                    'timestampCreated'         => date('Y-m-d H:i:s'),
                    'gibbonPersonIDCreated'    => $gibbonPersonIDCreated,
                ];

                $inserted = $this->enrolmentGateway->insert($data);
                if ($inserted) {
                    $results['total']++;
                    $results['inserted']++;
                    $results["choice".$choiceNumber]++;
                } else {
                    $results['error']++;
                }
            }
        }

        return $results;
    }

    protected function sortChoicesByWeighting(string $deepLearningEventID)
    {
        $choiceWeights = $this->choiceGateway->selectChoiceWeightingByEvent($deepLearningEventID)->fetchGroupedUnique();
        $yearGroupMax = $this->choiceGateway->getYearGroupWeightingMax();

        foreach ($this->choices as $gibbonPersonID => $person) {
            // Weight students who didn't get 1st choice in the past higher (0 - 3.0)
            $choiceWeight = ($choiceWeights[$gibbonPersonID]['choiceCount'] ?? 0) / max(($choiceWeights[$gibbonPersonID]['eventCount'] ?? 0), 1);

            // Students who are brand new to DL get an extra boost
            if (empty($choiceWeights[$gibbonPersonID]['eventCount'])) {
                $choiceWeight += 1.5;
            }

            // Weight younger year groups more than older ones (0 - 1.0)
            $yearGroupWeight = ($yearGroupMax - ($person['yearGroupSequence'] ?? 0)) / max($yearGroupMax, 1);

            // Add some randomization to keep things fresh (0 - 0.5)
            $randomWeight = (mt_rand(0,500) / 1000);

            $this->choices[$gibbonPersonID]['weight'] = $choiceWeight + $yearGroupWeight + $randomWeight;
        }

        // A higher weighting gives students a higher priority to get their top choices
        uasort($this->choices, function ($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });
    }
    
}
