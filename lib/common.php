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

/*
 * Funzioni globali utilizzate per il funzionamento dei componenti indipendenti del progetto (moduli, plugin, stampe, ...).
 *
 * @since 2.4.2
 */
use Common\Components\Accounting;

/**
 * Esegue una somma precisa tra due interi/array.
 *
 * @param array|float $first
 * @param array|float $second
 * @param int         $decimals
 *
 * @since 2.3
 *
 * @return float
 */
function sum($first, $second = null, $decimals = 4)
{
    $first = (array) $first;
    $second = (array) $second;

    $array = array_merge($first, $second);

    $result = 0;

    $decimals = is_numeric($decimals) ? $decimals : formatter()->getPrecision();

    $bcadd = function_exists('bcadd');

    foreach ($array as $value) {
        $value = round($value, $decimals);

        if ($bcadd) {
            $result = bcadd($result, $value, $decimals);
        } else {
            $result += $value;
        }
    }

    return floatval($result);
}

/**
 * Calcola gli sconti in modo automatico.
 *
 * @param array $data
 *
 * @return float
 */
function calcola_sconto($data)
{
    if ($data['tipo'] == 'PRC') {
        $result = 0;

        $price = floatval($data['prezzo']);

        $percentages = explode('+', $data['sconto']);
        foreach ($percentages as $percentage) {
            $discount = $price / 100 * floatval($percentage);

            $result += $discount;
            $price -= $discount;
        }
    } else {
        $result = floatval($data['sconto']);
    }

    if (!empty($data['qta'])) {
        $result = $result * $data['qta'];
    }

    return $result;
}

/**
 * Individua il valore della colonna order per i nuovi elementi di una tabella.
 *
 * @param $table
 * @param $field
 * @param $id
 *
 * @return mixed
 */
function orderValue($table, $field, $id)
{
    return database()->fetchOne('SELECT IFNULL(MAX(`order`) + 1, 1) AS value FROM '.$table.' WHERE '.$field.' = '.prepare($id))['value'];
}

/**
 * Ricalcola il riordinamento righe di una tabella.
 *
 * @param $table
 *
 * @return mixed
 */
function reorderRows($table, $field, $id)
{
    $righe = database()->select($table, 'id', [], [$field => $id], ['order' => 'ASC']);
    $i = 1;

    foreach ($righe as $riga) {
        database()->query('UPDATE '.$table.' SET `order`='.$i.' WHERE id='.prepare($riga['id']));
        ++$i;
    }
}

/**
 * Visualizza le informazioni relative allo sconto presente su una riga.
 *
 * @param bool $mostra_maggiorazione
 *
 * @return string|null
 */
function discountInfo(Accounting $riga, $mostra_maggiorazione = true)
{
    if (empty($riga->sconto_unitario) || (!$mostra_maggiorazione && $riga->sconto_unitario < 0)) {
        return null;
    }

    $text = ($riga->prezzo_unitario >= 0 && $riga->sconto_unitario > 0) || ($riga->prezzo_unitario < 0 && $riga->sconto_unitario < 0) ? tr('sconto _TOT_ _TYPE_') : tr('maggiorazione _TOT__TYPE_');
    $totale = !empty($riga->sconto_percentuale) ? $riga->sconto_percentuale : $riga->sconto_unitario_corrente;

    return replace($text, [
        '_TOT_' => Translator::numberToLocale(abs($totale)),
        '_TYPE_' => !empty($riga->sconto_percentuale) ? '%' : currency(),
    ]);
}

/**
 * Visualizza le informazioni relative allo provvigione presente su una riga.
 *
 * @param bool $mostra_provigione
 *
 * @return string|null
 */
function provvigioneInfo(Accounting $riga, $mostra_provigione = true)
{
    if (empty($riga->provvigione_unitaria) || (!$mostra_provigione && $riga->provvigione_unitaria < 0)) {
        return null;
    }

    $text = $riga->provvigione_unitaria > 0 ? tr('provvigione _TOT_ _TYPE_') : tr('provvigione _TOT__TYPE_');
    $totale = !empty($riga->provvigione_percentuale) ? $riga->provvigione_percentuale : $riga->provvigione_unitaria;

    return replace($text, [
        '_TOT_' => Translator::numberToLocale(abs($totale)),
        '_TYPE_' => !empty($riga->provvigione_percentuale) ? '%' : currency(),
    ]);
}

/**
 * Genera i riferimenti ai documenti del gestionale, attraverso l'interfaccia Common\ReferenceInterface.
 *
 * @param $document
 * @param string $text Formato "Contenuto descrittivo _DOCUMENT_"
 *
 * @return string
 */
function reference($document, $text = null)
{
    if (!empty($document) && !($document instanceof \Common\ReferenceInterface)) {
        return null;
    }

    $extra = '';
    $module_id = null;
    $document_id = null;

    if (empty($document)) {
        $content = tr('non disponibile');
        $extra = 'class="disabled"';
    } else {
        $module_id = $document->module;
        $document_id = $document->id;

        $content = $document->getReference();
    }

    $description = $text ?: tr('Rif. _DOCUMENT_', [
        '_DOCUMENT_' => strtolower($content),
    ]);

    return Modules::link($module_id, $document_id, $description, $description, $extra);
}

/**
 * Funzione che gestisce il parsing di uno sconto combinato e la relativa trasformazione in sconto fisso.
 * Esempio: (40 + 10) % = 44 %.
 *
 * @param $combinato
 *
 * @return float|int
 */
function parseScontoCombinato($combinato)
{
    $sign = substr($combinato, 0, 1);
    $original = $sign != '+' && $sign != '-' ? '+'.$combinato : $combinato;
    $pieces = preg_split('/[+,-]+/', $original);
    unset($pieces[0]);

    $result = 1;
    $text = $original;
    foreach ($pieces as $piece) {
        $sign = substr($text, 0, 1);
        $text = substr($text, 1 + strlen($piece));

        $result *= 1 - floatval($sign.$piece) / 100;
    }

    return (1 - $result) * 100;
}

/**
 * Visualizza le informazioni del segmento.
 *
 * @param $id_module
 *
 * @return float|int
 */
function getSegmentPredefined($id_module)
{
    $id_segment = database()->selectOne('zz_segments', 'id', ['id_module' => $id_module, 'predefined' => 1])['id'];

    return $id_segment;
}

/**
 * Funzione che visualizza i prezzi degli articoli nei listini.
 *
 * @param $id_anagrafica
 * @param $direzione
 * @param $id_articolo
 * @param $riga
 *
 * @return array
 */
function getPrezzoConsigliato($id_anagrafica, $direzione, $id_articolo, $riga = null)
{
    if ($riga) {
        $qta = $riga->qta;
        $prezzo_unitario_corrente = $riga->prezzo_unitario_corrente;
        $sconto_percentuale_corrente = $riga->sconto_percentuale;
    } else {
        $qta = 1;
    }
    $prezzi_ivati = setting('Utilizza prezzi di vendita comprensivi di IVA');
    $show_notifica_prezzo = null;
    $show_notifica_sconto = null;
    $prezzo_unitario = 0;
    $sconto = 0;

    // Prezzi netti clienti / listino fornitore
    $query = 'SELECT minimo, massimo,
        sconto_percentuale,
        '.($prezzi_ivati ? 'prezzo_unitario_ivato' : 'prezzo_unitario').' AS prezzo_unitario
    FROM mg_prezzi_articoli
    WHERE id_articolo = '.prepare($id_articolo).' AND dir = '.prepare($direzione).' AND id_anagrafica = '.prepare($id_anagrafica).'
    ORDER BY minimo ASC, massimo DESC';
    $prezzi = database()->fetchArray($query);

    // Prezzi listini clienti
    $query = 'SELECT sconto_percentuale AS sconto_percentuale_listino,
        '.($prezzi_ivati ? 'prezzo_unitario_ivato' : 'prezzo_unitario').' AS prezzo_unitario_listino
    FROM mg_listini
    LEFT JOIN mg_listini_articoli ON mg_listini.id=mg_listini_articoli.id_listino
    LEFT JOIN an_anagrafiche ON mg_listini.id=an_anagrafiche.id_listino
    WHERE mg_listini.data_attivazione<=NOW() 
    AND (mg_listini_articoli.data_scadenza>=NOW() OR (mg_listini_articoli.data_scadenza IS NULL AND mg_listini.data_scadenza_predefinita>=NOW()))
    AND mg_listini.attivo=1
    AND id_articolo = '.prepare($id_articolo).'
    AND dir = '.prepare($direzione).'
    AND idanagrafica = '.prepare($id_anagrafica);
    $listino = database()->fetchOne($query);

    // Prezzi listini clienti sempre visibili
    $query = 'SELECT mg_listini.nome, sconto_percentuale AS sconto_percentuale_listino_visibile,
        '.($prezzi_ivati ? 'prezzo_unitario_ivato' : 'prezzo_unitario').' AS prezzo_unitario_listino_visibile
    FROM mg_listini
    LEFT JOIN mg_listini_articoli ON mg_listini.id=mg_listini_articoli.id_listino
    WHERE mg_listini.data_attivazione<=NOW()
    AND (mg_listini_articoli.data_scadenza>=NOW() OR (mg_listini_articoli.data_scadenza IS NULL AND mg_listini.data_scadenza_predefinita>=NOW()))
    AND mg_listini.attivo=1 AND mg_listini.is_sempre_visibile=1 AND id_articolo = '.prepare($id_articolo).' AND dir = '.prepare($direzione);
    $listini_sempre_visibili = database()->fetchArray($query);

    if ($prezzi) {
        foreach ($prezzi as $prezzo) {
            if ($qta >= $prezzo['minimo'] && $qta <= $prezzo['massimo']) {
                $show_notifica_prezzo = $prezzo['prezzo_unitario'] != $prezzo_unitario_corrente ? true : $show_notifica_prezzo;
                $show_notifica_sconto = $prezzo['sconto_percentuale'] != $sconto_percentuale_corrente ? true : $show_notifica_sconto;
                $prezzo_unitario = $prezzo['prezzo_unitario'];
                $sconto = $prezzo['sconto_percentuale'];
                continue;
            }

            if ($prezzo['minimo'] == null && $prezzo['massimo'] == null && $prezzo['prezzo_unitario'] != null) {
                $show_notifica_prezzo = $prezzo['prezzo_unitario'] != $prezzo_unitario_corrente ? true : $show_notifica_prezzo;
                $show_notifica_sconto = $prezzo['sconto_percentuale'] != $sconto_percentuale_corrente ? true : $show_notifica_sconto;
                $prezzo_unitario = $prezzo['prezzo_unitario'];
                $sconto = $prezzo['sconto_percentuale'];
                continue;
            }
        }
    }
    if ($listino) {
        $show_notifica_prezzo = $listino['prezzo_unitario_listino'] != $prezzo_unitario_corrente ? true : $show_notifica_prezzo;
        $show_notifica_sconto = $listino['sconto_percentuale_listino'] != $sconto_percentuale_corrente ? true : $show_notifica_sconto;
        $prezzo_unitario = $listino['prezzo_unitario_listino'];
        $sconto = $listino['sconto_percentuale_listino'];
    }
    if ($listini_sempre_visibili) {
        foreach ($listini_sempre_visibili as $listino_sempre_visibile) {
            $show_notifica_prezzo = $listino_sempre_visibile['prezzo_unitario_listino_visibile'] != $prezzo_unitario_corrente ? true : $show_notifica_prezzo;
            $show_notifica_sconto = $listino_sempre_visibile['sconto_percentuale_listino_visibile'] != $sconto_percentuale_corrente ? true : $show_notifica_sconto;
        }
    }

    $result = [];
    $result['show_notifica_prezzo'] = $show_notifica_prezzo;
    $result['show_notifica_sconto'] = $show_notifica_sconto;
    $result['prezzo_unitario'] = $prezzo_unitario;
    $result['sconto'] = $sconto;

    return $result;
}
