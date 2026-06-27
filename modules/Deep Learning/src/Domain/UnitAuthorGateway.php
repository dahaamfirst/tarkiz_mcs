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

class UnitAuthorGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningUnitAuthor';
    private static $primaryKey = 'deepLearningUnitAuthorID';
    private static $searchableColumns = [''];

    public function selectAuthorsByUnit($deepLearningUnitID)
    {
        $data = ['deepLearningUnitID' => $deepLearningUnitID];
        $sql = "SELECT deepLearningUnitAuthor.deepLearningUnitAuthorID, deepLearningUnitAuthor.timestamp, gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName
                FROM deepLearningUnitAuthor
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=deepLearningUnitAuthor.gibbonPersonID) 
                WHERE deepLearningUnitAuthor.deepLearningUnitID=:deepLearningUnitID
                ORDER BY deepLearningUnitAuthor.timestamp DESC";

        return $this->db()->select($sql, $data);
    }

    public function deleteAuthorsNotInList($deepLearningUnitID, $authorIDList)
    {
        $authorIDList = is_array($authorIDList) ? implode(',', $authorIDList) : $authorIDList;

        $data = ['deepLearningUnitID' => $deepLearningUnitID, 'authorIDList' => $authorIDList];
        $sql = "DELETE FROM deepLearningUnitAuthor WHERE deepLearningUnitID=:deepLearningUnitID AND NOT FIND_IN_SET(deepLearningUnitAuthorID, :authorIDList)";

        return $this->db()->delete($sql, $data);
    }
}
