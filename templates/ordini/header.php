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

echo '
$default_header$

<div class="row">

    <!-- Dati Ordine -->
    <div class="col-xs-6">
		<div class="text-center" style="height:5mm;">
			<b>$tipo_doc$</b>
		</div>
        <br>

		<table class="table">
			<tr>
				<td valign="top" class="border-bottom border-top text-center">
					<p class="small-bold text-muted">'.tr('Nr. documento', [], ['upper' => true]).'</p>
					<p>$numero$</p>
				</td>

				<td class="border-bottom border-top text-center">
					<p class="small-bold text-muted">'.tr('Data documento', [], ['upper' => true]).'</p>
					<p>$data$</p>
				</td>

				<td class="border-bottom border-top center text-center">
					<p class="small-bold text-muted">'.tr('Foglio', [], ['upper' => true]).'</p>
					<p>{PAGENO}/{nb}</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Dati Cliente/Fornitore -->
	<div class="col-xs-6" style="margin-left: 10px">
        <table class="table border-bottom" >
            <tr>
                <td colspan="2" style="height:20mm;">
                    <p class="small-bold text-muted">'.tr('Spett.le', [], ['upper' => true]).'</p>
                    <p>$c_ragionesociale$</p>
					<p>$c_indirizzo$<br> $c_citta_full$</p>
					<p>$c_telefono$ $c_cellulare$</p>
                </td>
            </tr>';
if (!empty($destinazione)) {
    echo '
			<tr>
				<td colspan=2 class="border-full" style="height:16mm;">
					<p class="small-bold text-muted">'.tr('Destinazione diversa', [], ['upper' => true]).'</p>
					'.$destinazione.'
				</td>
			</tr>';
}
echo '
        </table>
    </div>
</div>';
