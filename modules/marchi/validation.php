<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

include_once __DIR__.'/../../core.php';

$name = filter('name');
$value = filter('value');

switch ($name) {
    case 'name':
        $disponibile = $dbo->table('mg_marchi')->where([
            ['name', $value],
            ['id', '<>', $id_record],
        ])->count() == 0;

        $message = $disponibile ? tr('Il name è disponbile') : tr('Il name è già utilizzato in un altro marchio');

        $response = [
            'result' => $disponibile,
            'message' => $message,
        ];

        break;
}
