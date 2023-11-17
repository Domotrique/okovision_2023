var lang = {
    error:{
        communication: 'Problème de communication',
        getFileFromBoiler: 'Impossible de récupérer les fichiers présents sur la chaudière',
        maj: 'Echec de la mise à jour',
        csvImport: "Echec de l'importation",
        summary: 'Synthèse non traitée',
        ipNotPing: "L'adresse Ip ne repond pas",
        configNotSave: 'Configuration non sauvegardée',
        csvNotFound: "Le fichier CSV de référence n'a pas été trouvé",
        getSeasons: 'Problème lors de la récupération des saisons',
        saveSeason: "Problème lors de l'enregistrement de la saison",
        seasonAlreadyExist: 'Attention, cette saison existe déjà !',
        deleteSeason: 'Problème lors de la suppresion de la saison',
        getEvents: "Problème lors de la récupération des événements",
        saveEvent: "Problème lors de l'enregistrement de l'événement",
        deleteEvent: "Problème lors de la suppresion de l'événement",
        date: 'Format de la date incorrect',
        dateInvert: 'La date de début doit etre inferieur à la date de fin',
        save: 'Problème lors de la sauvegarde',
        position: 'Impossible de récupérer la derniere position',
        grapehAlreadyExist: 'Attention, le graphe existe déjà !',
        deleteGraphe: "Problème lors de la suppresion du graphe",
        assoAlreadyExist: "Attention, le couple Graphique + Capteur existe déjà",
        coeffMustBeNumber: "Le coefficient doit etre un nombre",
        update: "Problème lors de la mise à jour",
        deleteAsso: "Problème lors de la suppresion de l'association",
        getGraphe: "Impossible de charger la liste des graphiques",
        getAsso: "Impossible de charger la liste des associations",
        getSensor: "Impossible de récupérer la liste des capteurs",
        getIndicByMonth: "Problème lors de la récupération des indicateurs du mois",
        getSiloStatus: "Problème lors de la récupération du statut du silo",
        getTotalSaison: "Problème lors de la récupération des indicateurs de la saison",
        getSyntheseSaison: "Problème lors de la récupération de la synthèse de la saison",
        bddFail: "Echec de connexion à la base de données",
        passNotChanged: "Mot de passe inchangé !",
        passNotTheSame: "Les deux champs ne sont pas identiques.",
        userPassIncorrect: "User/password incorrect",
        sessionEnded: 'Session expirée',
        connectBoiler: 'Echec de connexion (chaudière)',
        getListConfigBoiler: 'Impossible de récuperer la liste des configurations',
        commentConfigBoiler: 'La description ne doit pas être vide',
        saveBoilerConfig: 'Impossible de sauvegarder la configuration',
        deleteBoilerConfig: 'Suppression impossible',
        deleteMatrix : "Echec lors de la suppression de la matrice"
    },
    valid:{
        communication: 'Communication établie',
        maj: 'Mise à jour réalisée avec succès',
        csvImport: 'Importation réussie',
        summary: 'Synthèse réussie',
        configSave: 'Configuration sauvegardée',
        save: 'Enregistrement réussi',
        update: 'Mise à jour réussie',
        delete: 'Suppression réussie',
        applyConfigboiler: 'Configuration appliquée sur la chaudière'
    },
    text:{
        seeFileOnboiler: 'Visualiser les fichiers sur la chaudière',
        addSeason: "Ajout d'une saison",
        updateSeason: 'Modification saison',
        deleteSeason: 'Confirmez-vous la suppression de la saison',
        addEvent: "Ajout d'un événement",
        updateEvent: "Modification d'un événement",
        deleteEvent: "Confirmez-vous la suppression de l'événement",        
        eventTypePellets: "Remplissage du silo",
        eventTypeAshes: "Vidage du cendrier",
        eventTypeMaintenance: "Maintenance de la chaudière",
        eventTypeChimneySweeping: "Ramonage",
        eventTypeBag: "Ajout de sacs de pellet",        
        eventPelletsdetails: "{0} kg, {1}€ ({2}€/T)",
        eventBagDetails: "{0} kg, {1}€ ({2}€/15Kg)",
        eventmaintenanceDetails: "{0}€",
        firstSetup: "Aucune valeur n'est remontée par votre chaudière. Vérifiez que vous avez bien tout paramétré",
        addGraphe: "Création d'un nouveau graphique",
        updateGraphe: 'Modification de',
        deleteGraphe: 'Confirmez-vous la suppresion de',
        updateAsso: "Modification de l'association",
        deleteAsso: "Confirmez-vous la suppresion de l'asso",
        titreHisto: 'Synthèse mensuelle',
        estimatedEmptyDate: 'Date estimée de silo vide : {0}',
        estimationReliability: "Estimation basée sur l'historique des consommations. Fiabilité : {0}%",
        no_silo_size: 'Pour connaître le statut de remplissage de votre silo, veuillez renseigner sa contenance <a href="adminParam.php">dans les informations générales</a>',
        no_fill_date_for_silo: 'Pour connaître l\'état de votre stock de pellet, veuillez renseigner <a href="adminEvents.php">les informations du dernier remplissage</a>',
        updateAvailable: 'Une nouvelle version est disponible, cliquez ici'
    },
    graphic:{
        thousandsSep: ' ',
        decimalPoint: ',',
        months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
        shortMonths: [ "Jan" , "Fev" , "Mar" , "Avr" , "Mai" , "Juin" , "Juil" , "Aout" , "Sep" , "Oct" , "Nov" , "Dec"],
        weekdays: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
        day: 'jour',
        month: 'mois',
        hour: 'Heures',
        tc: 'T°C',
        kgAndDju: 'Kg et DJU',
        nbCycle: 'Nb Cycle',
        seasonSummary: "Synthèse Saison",
        loading : 'Loading data from boiler...'
    }
    /*,
    sensor:{
        FA0_L_mittlere_laufzeit:"CAPPL:FA[0].L_mittlere_laufzeit",
        FA0_L_brennerstarts:"CAPPL:FA[0].L_brennerstarts",
        FA0_L_brennerlaufzeit_anzeige:"CAPPL:FA[0].L_brennerlaufzeit_anzeige",
        FA0_L_anzahl_zuendung:"CAPPL:FA[0].L_anzahl_zuendung",
        touch0_version:"CAPPL:LOCAL.touch[0].version",
        hk0_raumtemp_heizen:"CAPPL:LOCAL.hk[0].raumtemp_heizen",
        hk0_raumtemp_absenken:"CAPPL:LOCAL.hk[0].raumtemp_absenken",
        hk0_heizkurve_steigung:"CAPPL:LOCAL.hk[0].heizkurve_steigung",
        hk0_heizkurve_fusspunkt:"CAPPL:LOCAL.hk[0].heizkurve_fusspunkt",
        hk0_heizgrenze_heizen:"CAPPL:LOCAL.hk[0].heizgrenze_heizen",
        hk0_heizgrenze_absenken:"CAPPL:LOCAL.hk[0].heizgrenze_absenken",
        hk0_vorlauftemp_max:"CAPPL:LOCAL.hk[0].vorlauftemp_max",
        hk0_vorlauftemp_min:"CAPPL:LOCAL.hk[0].vorlauftemp_min",
        hk0_ueberhoehung:"CAPPL:LOCAL.hk[0].ueberhoehung",
        hk0_mischer_max_auf_zeit:"CAPPL:LOCAL.hk[0].mischer_max_auf_zeit",
        hk0_mischer_max_aus_zeit:"CAPPL:LOCAL.hk[0].mischer_max_aus_zeit",
        hk0_mischer_max_zu_zeit:"CAPPL:LOCAL.hk[0].mischer_max_zu_zeit",
        hk0_mischer_regelbereich_quelle:"CAPPL:LOCAL.hk[0].mischer_regelbereich_quelle",
        hk0_mischer_regelbereich_vorlauf:"CAPPL:LOCAL.hk[0].mischer_regelbereich_vorlauf",
        hk0_quellentempverlauf_anstiegstemp:"CAPPL:LOCAL.hk[0].quellentempverlauf_anstiegstemp",
        hk0_quellentempverlauf_regelbereich:"CAPPL:LOCAL.hk[0].quellentempverlauf_regelbereich",
        FA0_pe_kesseltemperatur_soll:"CAPPL:FA[0].pe_kesseltemperatur_soll",
        FA0_pe_abschalttemperatur:"CAPPL:FA[0].pe_abschalttemperatur",
        FA0_pe_einschalthysterese_smart:"CAPPL:FA[0].pe_einschalthysterese_smart",
        FA0_pe_kesselleistung:"CAPPL:FA[0].pe_kesselleistung"
    }*/
};
    
