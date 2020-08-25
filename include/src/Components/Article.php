<?php

namespace Common\Components;

use Common\Document;
use Illuminate\Database\Eloquent\Builder;
use Modules\Articoli\Articolo as Original;
use Modules\Articoli\Movimento;
use Plugins\DettagliArticolo\DettaglioFornitore;
use UnexpectedValueException;

abstract class Article extends Row
{
    public $movimenta_magazzino = true;
    protected $abilita_movimentazione = true;

    protected $serialRowID = null;
    protected $serialsList = null;

    protected $qta_movimentazione = 0;

    public static function build(Document $document, Original $articolo)
    {
        $model = parent::build($document, true);

        $model->articolo()->associate($articolo);

        $model->descrizione = $articolo->descrizione;
        $model->abilita_serial = $articolo->abilita_serial;
        $model->um = $articolo->um;

        return $model;
    }

    public function movimenta($qta)
    {
        if (!$this->movimenta_magazzino) {
            return;
        }

        $movimenta = true;

        // Movimenta il magazzino solo se l'articolo non è già stato movimentato da un documento precedente
        if ($this->hasOriginal()) {
            $original = $this->getOriginal();
            $movimenta = !$original->movimenta_magazzino;
        }

        if ($movimenta) {
            $this->movimentaMagazzino($qta);
        }
    }

    public function getDirection()
    {
        return $this->parent->direzione;
    }

    /**
     * Restituisce il codice impostato per l'articolo corrente.
     */
    public function getCodiceAttribute()
    {
        return $this->dettaglioFornitore->codice_fornitore ?: $this->articolo->codice;
    }

    /**
     * Imposta i seriali collegati all'articolo.
     *
     * @param array $serials
     */
    public function setSerialsAttribute($serials)
    {
        if (!$this->useSerials()) {
            return;
        }

        $serials = array_clean($serials);

        database()->sync('mg_prodotti', [
            'id_riga_'.$this->serialRowID => $this->id,
            'dir' => $this->getDirection(),
            'id_articolo' => $this->idarticolo,
        ], [
            'serial' => $serials,
        ]);

        $this->serialsList = $serials;
    }

    /**
     * Rimuove i seriali collegati all'articolo.
     *
     * @param array $serials
     */
    public function removeSerials($serials)
    {
        if (!$this->useSerials()) {
            return;
        }

        database()->detach('mg_prodotti', [
            'id_riga_'.$this->serialRowID => $this->id,
            'dir' => $this->getDirection(),
            'id_articolo' => $this->idarticolo,
        ], [
            'serial' => array_clean($serials),
        ]);

        $this->serialsList = null;
    }

    /**
     * Restituisce l'elenco dei seriali collegati all'articolo del documento.
     *
     * @return array
     */
    public function getSerialsAttribute()
    {
        if (!$this->useSerials()) {
            return [];
        }

        if (!isset($this->serialsList)) {
            // Individuazione dei seriali
            $results = database()->fetchArray('SELECT serial FROM mg_prodotti WHERE serial IS NOT NULL AND id_riga_'.$this->serialRowID.' = '.prepare($this->id));

            $this->serialsList = array_column($results, 'serial');
        }

        return $this->serialsList;
    }

    /**
     * Restituisce il numero di seriali mancanti per il completamento dell'articolo.
     *
     * @return float
     */
    public function getMissingSerialsNumberAttribute()
    {
        if (!$this->useSerials()) {
            return 0;
        }

        $missing = $this->qta - count($this->serials);

        return $missing;
    }

    /**
     * Modifica la quantità dell'articolo e movimenta automaticamente il magazzino.
     *
     * @param float $value
     */
    public function setQtaAttribute($value)
    {
        if (!$this->cleanupSerials($value)) {
            throw new UnexpectedValueException();
        }

        $diff = parent::setQtaAttribute($value);

        if ($this->abilita_movimentazione) {
            $this->qta_movimentazione += $diff;
        }
    }

    public function articolo()
    {
        return $this->belongsTo(Original::class, 'idarticolo');
    }

    public function dettaglioFornitore()
    {
        return $this->belongsTo(DettaglioFornitore::class, 'id_dettaglio_fornitore')->withTrashed();
    }

    public function movimentazione($value = true)
    {
        $this->abilita_movimentazione = $value;
    }

    /**
     * Salva l'articolo, eventualmente movimentandone il magazzino.
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        if (!empty($this->qta_movimentazione)) {
            $this->movimenta($this->qta_movimentazione);
            $this->qta_movimentazione = 0;
        }

        return parent::save($options);
    }

    public function canDelete()
    {
        $serials = $this->usedSerials();

        return empty($serials);
    }

    public function delete()
    {
        if (!$this->canDelete()) {
            throw new \InvalidArgumentException();
        }

        $this->serials = [];

        $this->qta = 0; // Fix movimentazione automatica
        if (!empty($this->qta_movimentazione)) {
            $this->movimenta($this->qta_movimentazione);
        }

        return parent::delete();
    }

    protected function useSerials()
    {
        return !empty($this->abilita_serial) && !empty($this->serialRowID);
    }

    protected function movimentaMagazzino($qta)
    {
        $documento = $this->parent;
        $data = $documento->getReferenceDate();

        $qta_movimento = $documento->direzione == 'uscita' ? $qta : -$qta;
        $movimento = Movimento::descrizioneMovimento($qta_movimento, $documento->direzione).' - '.$documento->getReference();

        $partenza = $documento->direzione == 'uscita' ? $documento->idsede_destinazione : $documento->idsede_partenza;
        $arrivo = $documento->direzione == 'uscita' ? $documento->idsede_partenza : $documento->idsede_destinazione;

        // Fix per valori di sede a NULL
        $partenza = $partenza ?: 0;
        $arrivo = $arrivo ?: 0;

        $this->articolo->movimenta($qta_movimento, $movimento, $data, false, [
            'reference_type' => get_class($documento),
            'reference_id' => $documento->id,
            'idsede_azienda' => $partenza,
            'idsede_controparte' => $arrivo,
        ]);
    }

    protected static function boot()
    {
        // Precaricamento Articolo
        static::addGlobalScope('articolo', function (Builder $builder) {
            $builder->with('articolo', 'dettaglioFornitore');
        });

        parent::boot(true);

        $table = parent::getTableName();

        static::addGlobalScope('articles', function (Builder $builder) use ($table) {
            $builder->whereNotNull($table.'.idarticolo')->where($table.'.idarticolo', '<>', 0);
        });
    }

    /**
     * Restituisce l'elenco dei seriali collegati e utilizzati da altri documenti.
     *
     * @return array
     */
    protected function usedSerials()
    {
        if (!$this->useSerials()) {
            return [];
        }
        if ($this->getDirection() == 'uscita') {
            $results = database()->fetchArray("SELECT serial FROM mg_prodotti WHERE serial IN (SELECT DISTINCT serial FROM mg_prodotti WHERE dir = 'entrata') AND serial IS NOT NULL AND id_riga_".$this->serialRowID.' = '.prepare($this->id));

            return array_column($results, 'serial');
        }

        return [];
    }

    /**
     * Pulisce i seriali non utilizzati nel caso di riduzione della quantità, se possibile.
     *
     * @param $new_qta
     *
     * @return bool
     */
    protected function cleanupSerials($new_qta)
    {
        if (!$this->useSerials()) {
            return true;
        }

        // Se la nuova quantità è minore della precedente
        if ($this->qta > $new_qta) {
            $seriali_usati = $this->usedSerials();
            $count_seriali_usati = count($seriali_usati);

            // Controllo sulla possibilità di rimuovere i seriali (se non utilizzati da documenti di vendita)
            if ($this->getDirection() == 'uscita' && $new_qta < $count_seriali_usati) {
                return false;
            } else {
                // Controllo sul numero di seriali effettivi da rimuovere
                $seriali = $this->serials;

                if ($new_qta < count($seriali)) {
                    $rimovibili = array_diff($seriali, $seriali_usati);

                    // Rimozione dei seriali aggiuntivi
                    $serials = array_slice($rimovibili, 0, $new_qta - $count_seriali_usati);

                    $this->serials = array_merge($seriali_usati, $serials);
                }
            }
        }

        return true;
    }

    protected function customInitCopiaIn($original)
    {
        $this->articolo()->associate($original->articolo);
    }

    protected function customBeforeDataCopiaIn($original)
    {
        //$this->movimentazione(false);

        parent::customBeforeDataCopiaIn($original);
    }

    protected function customAfterDataCopiaIn($original)
    {
        //        $this->movimentazione(true);

        parent::customAfterDataCopiaIn($original);
    }
}
