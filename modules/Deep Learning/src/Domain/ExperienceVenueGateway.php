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

namespace Gibbon\Module\DeepLearning\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class ExperienceVenueGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningExperienceVenue';
    private static $primaryKey = 'deepLearningExperienceVenue';
    private static $searchableColumns = [];


    public function selectVenuesByExperience($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "SELECT deepLearningExperienceVenue.deepLearningExperienceVenueID, 
                    deepLearningExperienceVenue.deepLearningEventDateID, 
                    deepLearningExperienceVenue.gibbonSpaceID, 
                    deepLearningExperienceVenue.venueExternal, 
                    deepLearningExperienceVenue.venueExternalUrl, 
                    deepLearningExperienceVenue.description, 
                    deepLearningExperienceVenue.allDay, 
                    deepLearningExperienceVenue.timeStart, 
                    deepLearningExperienceVenue.timeEnd,
                    deepLearningEventDate.eventDate
                FROM deepLearningExperienceVenue
                LEFT JOIN deepLearningEventDate ON (deepLearningEventDate.deepLearningEventDateID=deepLearningExperienceVenue.deepLearningEventDateID)
                WHERE deepLearningExperienceID=:deepLearningExperienceID
                ORDER BY deepLearningEventDate.eventDate, deepLearningExperienceVenue.timeStart";

        return $this->db()->select($sql, $data);
    }
}
