<?php
/*
* Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
*/

class capteur extends connectDb
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * Function geting all sensor.
     *
     * @return json (id, name, position_column_csv, column_oko, original_name, type)
     */
    public function getAll()
    {
        $result = $this->query('select id, name, position_column_csv, column_oko, original_name, type from oko_capteur;');
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Function geting on sensor  by is own id.
     *
     * param integer id
     *
     * @param mixed $id
     *
     * @return json (id, name, position_column_csv, column_oko, original_name, type)
     */
    public function get($id)
    {
        $capteur = [];
        if (null != $id) {
            $q = 'select id, name, position_column_csv, column_oko, original_name, type from oko_capteur where id= ?';

            $result = $this->prepared($q, 'i', $id);
            if (!$result) {
                return $capteur;
            }
            $capteur = $result->fetch_assoc();
        }

        return $capteur;
    }

    public function getForImportCsv()
    {
        $result = $this->query('select id, name, position_column_csv, column_oko, original_name, type from oko_capteur where position_column_csv <> -1;');
        while ($row = $result->fetch_assoc()) {
            $r[$row['position_column_csv']] = $row;
        }

        return $r;
    }

    public function getMatrix()
    {
        $r = [];
        
        $result = $this->query("select id, name, position_column_csv, column_oko, original_name, type from oko_capteur where type <> 'startCycle' order by position_column_csv asc;");
        while ($row = $result->fetch_object()) {
            $r[$row->original_name] = $row;
        }

        return $r;
    }

    public function getByType($type = '')
    {
        if ('' != $type) {
            $q = "select id, name, position_column_csv, column_oko, original_name, type from oko_capteur where type = ?";
            $result = $this->prepared($q, 's', $type);

            if (!$result) {
                return;
            }

            return $result->fetch_assoc();
        }
    }

    public function getLastColumnOko()
    {
        $result = $this->query('select max(column_oko) as num from oko_capteur;');
        $r = $result->fetch_object();
        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | Update oko_capteur | '.$r->num);

        return $r->num;
    }
}
