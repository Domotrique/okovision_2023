<?php

class gstGraphique extends connectDb
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function getGraphe()
    {
        $q = 'select id, name, position from oko_graphe order by position';

        $result = $this->query($q);

        if ($result) {
            $r['response'] = true;

            $tmp = [];
            while ($res = $result->fetch_object()) {
                array_push($tmp, $res);
            }
            $r['data'] = $tmp;
        } else {
            $r['response'] = false;
        }

        $this->sendResponse($r);
    }

    public function getLastGraphePosition()
    {
        $q = 'select max(position) as lastPosition from oko_graphe';
        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q); //.$r['data']['lastPosition']

        $result = $this->query($q);

        $r['response'] = false;

        if ($result) {
            $r['response'] = true;
            $r['data'] = $result->fetch_object();
        }

        $this->sendResponse($r);
    }

    public function grapheNameExist($name)
    {
        $q = "select count(*) from oko_graphe where name = ?";

        $result = $this->prepared($q, 's', $name);

        $r['exist'] = false;
        if ($result) {
            $res = $result->fetch_row();
            if ($res[0] > 0) {
                $r['exist'] = true;
            }
        }
        $this->sendResponse($r);
    }

    public function addGraphe($s)
    {
        $q = "INSERT INTO oko_graphe (name, position) VALUES (?, ?)";

        $r['response'] = $this->prepared($q, 'si', $s['name'], $s['position']);

        $this->log->debug('Class gestGraphique | addGraphe | '.$q);

        $this->sendResponse($r);
    }

    public function updateGraphe($s)
    {
        $q = "UPDATE oko_graphe SET name= ? where id= ?";

        $r['response'] = $this->prepared($q, 'si', $s['name'], $s['id']);

        $this->sendResponse($r);
    }

    public function updateGraphePosition($s)
    {
        $r['response'] = false;
        //si position des autres est = ou sup alors on fait + 1, si position est inf on fait -1
        //on met a jour la position du grpahe selectionné

        $q = "UPDATE oko_graphe SET position = ? WHERE id = ?";

        $detect = $this->prepared($q, 'ii', $s['position'], $s['id_graphe']);

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        if ($detect) {
            if ($s['position'] > $s['current']) {
                $q = "UPDATE oko_graphe SET position=(position - 1) WHERE position <= ? AND position > ? AND id <> ?";
            } else {
                $q = "UPDATE oko_graphe SET position=(position + 1) WHERE position >= ? AND position < (? + 1) AND id <> ?";
            }
            $r['response'] = $this->prepared($q, 'iii', $s['position'], $s['current'], $s['id_graphe']);
            $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);
        }

        $this->sendResponse($r);
    }

    public function deleteGraphe($s)
    {
        $r['response'] = false;

        $q = "SELECT position from oko_graphe where id= ?";

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $result = $this->prepared($q, 'i', $s['id']);

        if ($result) {
            $res = $result->fetch_object();
            if ($res) {
                $position = $res->position;

                $q = "DELETE from oko_graphe where id= ?";

                $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

                if ($this->prepared($q, 'i', $s['id'])) {
                    $q = "UPDATE oko_graphe SET position = (position - 1) WHERE position > ?";
                    
                    $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

                    $r['response'] = $this->prepared($q, 'i', $position);
                }
            } else {
                $r['response'] = false;
            }
        }

        $this->sendResponse($r);
    }

    public function getCapteurs()
    {
        $q = 'select id, name from oko_capteur order by id asc';
        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $result = $this->query($q);

        if ($result) {
            $r['response'] = true;

            $tmp = [];
            while ($res = $result->fetch_object()) {
                array_push($tmp, $res);
            }
            $r['data'] = $tmp;
        } else {
            $r['response'] = false;
        }

        $this->sendResponse($r);
    }

    public function grapheAssoCapteurExist($graphe, $capteur)
    {
        $q = "select count(*) from oko_asso_capteur_graphe where oko_graphe_id= ? and oko_capteur_id= ?";

        $result = $this->prepared($q, 'ii', $graphe, $capteur);

        $r['exist'] = false;
        if ($result) {
            $res = $result->fetch_row();

            if ($res[0] > 0) {
                $r['exist'] = true;
            }
        }
        $this->sendResponse($r);
    }

    public function addGrapheAsso($s)
    {
        $q = 'INSERT INTO oko_asso_capteur_graphe (oko_graphe_id, oko_capteur_id, position, correction_effect) VALUES (?, ?, ?, ?)';

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $r = [];
        $r['response'] = $this->prepared($q, 'iiis', $s['id_graphe'], $s['id_capteur'], $s['position'], $s['coeff']);
        
        $this->sendResponse($r);
    }

    public function getGrapheAsso($grapheId)
    {
        $q = 'SELECT capteur.id, capteur.name, asso.correction_effect as coeff from oko_asso_capteur_graphe as asso '.
            'LEFT JOIN oko_capteur as capteur ON asso.oko_capteur_id = capteur.id '
            .'WHERE asso.oko_graphe_id = ? ORDER BY asso.position';

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $result = $this->prepared($q, 'i', $grapheId);

        if ($result) {
            $r['response'] = true;

            $tmp = [];
            while ($res = $result->fetch_object()) {
                array_push($tmp, $res);
            }
            $r['data'] = $tmp;
        } else {
            $r['response'] = false;
        }

        $this->sendResponse($r);
    }

    public function updateGrapheAsso($s)
    {
        $q = 'UPDATE oko_asso_capteur_graphe SET correction_effect = ? where oko_graphe_id = ? AND oko_capteur_id = ?';

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $r['response'] = $this->prepared($q, 'sii', $s['coeff'], $s['id_graphe'], $s['id_capteur']);

        $this->sendResponse($r);
    }

    public function updateGrapheAssoPosition($s)
    {
        $r['response'] = false;
        //si position des autres est = ou sup alors on fait + 1, si position est inf on fait -1
        //on met a jour la position du graphe selectionné
        $q = 'UPDATE oko_asso_capteur_graphe SET position = ? WHERE oko_graphe_id = ? AND oko_capteur_id = ?';
        
        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        if ($this->prepared($q, 'iii', $s['position'], $s['id_graphe'], $s['id_capteur'])) {
            if ($s['position'] > $s['current']) {
                $q = 'UPDATE oko_asso_capteur_graphe SET position=(position - 1) WHERE position <= ? AND position > ? AND oko_graphe_id = ? AND oko_capteur_id <> ?';
            } else {
                $q = 'UPDATE oko_asso_capteur_graphe SET position=(position + 1) WHERE position >= ? AND position < (? + 1) AND oko_graphe_id = ? AND oko_capteur_id <> ?';
            }
            $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

            $r['response'] = $this->prepared($q, 'iiii', $s['position'], $s['current'], $s['id_graphe'], $s['id_capteur']);
        }

        $this->sendResponse($r);
    }

    public function deleteAssoGraphe($s)
    {
        $r['response'] = false;
        //on recupere la position du capteur dans le graphe
        $q = 'SELECT position from oko_asso_capteur_graphe WHERE oko_graphe_id = ? AND oko_capteur_id = ?';

        $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

        $result = $this->prepared($q, 'ii', $s['id_graphe'], $s['id_capteur']);

        if ($result) {
            $res = $result->fetch_object();
            if ($res) {
                $position = $res->position;

                $q = 'DELETE FROM oko_asso_capteur_graphe WHERE oko_graphe_id = ? AND oko_capteur_id = ?';
                
                $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

                if ($this->prepared($q, 'ii', $s['id_graphe'], $s['id_capteur'])) {
                    $q = 'UPDATE oko_asso_capteur_graphe SET position = (position - 1) WHERE position > ? AND oko_graphe_id = ?';
                    
                    $this->log->debug('Class '.__CLASS__.' | '.__FUNCTION__.' | '.$q);

                    $r['response'] = $this->prepared($q, 'ii', $position, $s['id_graphe']);
                }
            } else {
                $r['response'] = false;
            }
        }

        $this->sendResponse($r);
    }

    private function sendResponse($t)
    {
        header('Content-type: text/json');
        echo json_encode($t, JSON_NUMERIC_CHECK);
    }
}
