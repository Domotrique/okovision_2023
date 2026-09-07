<?php
/*
* Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
*/

class connectDb
{
    protected $log;

    private $db;
    private $_ip = BDD_IP;
    private $_user = BDD_USER;
    private $_pass = BDD_PASS;
    private $_schema = BDD_SCHEMA;

    private static $_instance; //The single instance

    public function __construct()
    {
        $this->log = new logger();
    }

    // Destructor
    public function __destruct()
    {
        //$this->disconnect();
    }

    // Magic method clone is empty to prevent duplication of connection
    private function __clone()
    {
    }

    // Get mysqli connection
    protected function getConnection()
    {
        if (null == $this->db) {
            $this->connect();
        }

        return $this->db;
    }

    protected function realEscapeString($s)
    {
        $con = self::getInstance()->getConnection();

        return $con->real_escape_string($s);
    }

    /**
     * Exécute une requête préparée.
     *
     * Le contrat de retour est identique à query() : mysqli_result pour un
     * SELECT, true pour du DML réussi, false en cas d'échec.
     *
     * @param string $sql    requête à placeholders '?'
     * @param string $types  chaîne bind_param : i (int), d (float), s (string), b (blob)
     * @param mixed  ...$params valeurs, dans l'ordre d'apparition des '?'
     *
     * @return mysqli_result|bool
     */
    protected function prepared($sql, $types = '', ...$params)
    {
        if (strlen($types) !== count($params)) {
            $this->log->error('GLOBAL | prepared | nbr d`arguments invalide : types "'.$types.'" pour '.count($params).' paramètre(s) | '.$sql);

            return false;
        }

        $con = self::getInstance()->getConnection();
        $stmt = null;

        try {
            $stmt = $con->prepare($sql);

            if (false === $stmt) {
                $this->log->error('GLOBAL | prepared | prepare() : '.$con->error.' | '.$sql);

                return false;
            }

            if ('' !== $types) {
                $stmt->bind_param($types, ...$params);
            }

            if (!$stmt->execute()) {
                $this->log->error('GLOBAL | prepared | execute() : '.$stmt->error.' | '.$sql);
                $stmt->close();

                return false;
            }

            $result = $stmt->get_result();

            // get_result() renvoie false pour du DML : ce n'est une erreur
            // que si errno est non nul.
            if (false === $result) {
                $ok = (0 === $stmt->errno);

                if (!$ok) {
                    $this->log->error('GLOBAL | prepared | get_result() : '.$stmt->error.' | '.$sql);
                }
                $stmt->close();

                return $ok;
            }

            $stmt->close();

            return $result;
        } catch (mysqli_sql_exception $e) {
            $this->log->error('GLOBAL | prepared | '.$e->getMessage().' | '.$sql);

            if ($stmt instanceof mysqli_stmt) {
                @$stmt->close();
            }

            return false;
        }
    }

    protected function query($q)
    {
        $con = self::getInstance()->getConnection();

        return $con->query($q);
    }

    protected function multi_query($q)
    {
        $con = self::getInstance()->getConnection();

        return $con->multi_query($q);
    }

    protected function flush_multi_queries()
    {
        $con = self::getInstance()->getConnection();

        return $con->next_result() && $con->more_results();
    }

    private static function getInstance()
    {
        if (!self::$_instance) { // If no instance then make one
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function connect()
    {
        $this->db = new mysqli($this->_ip, $this->_user, $this->_pass, $this->_schema);

        if ($this->db->connect_errno) {
            $this->log->error('GLOBAL | Connection MySQL impossible : '.$this->db->connect_error);
            exit(22);
        }

        if (!$this->db->set_charset('utf8')) {
            $this->log->error('GLOBAL | Erreur lors du chargement du jeu de caractères utf8 :'.$this->db->error);
        }

        $this->query("SET time_zone='+00:00'");
        $this->query("SET @@SESSION.SQL_MODE = REPLACE(@@SQL_MODE, 'ONLY_FULL_GROUP_BY,', '')");
    }

    private function disconnect()
    {
        $this->db->close();
    }
}
