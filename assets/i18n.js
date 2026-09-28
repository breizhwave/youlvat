/* Régie Bénévoles — traductions (français / brezhoneg).
   Le texte français sert de clé : t('Planning') renvoie la traduction bretonne si la langue est « br »,
   sinon le texte français tel quel. Variables : t('manque {n}',{n:3}).
   Pluriels : tn(n,'{n} créneau','{n} créneaux') (en breton, le nom reste au singulier après un nombre).
   Langue : ?lang=br|fr dans l'URL (mémorisé), sinon localStorage « rb.lang », sinon français.
   Traductions bretonnes à faire relire par un·e brittophone. */
(function(){
const LANGS=[['br','BZH','BR'],['fr','FR','FR']];
const ls={get(k){try{return localStorage.getItem(k)}catch(e){return null}},set(k,v){try{localStorage.setItem(k,v)}catch(e){}}};

let lang='fr';
const fromUrl=new URLSearchParams(location.search).get('lang');
if(fromUrl==='br'||fromUrl==='fr'){lang=fromUrl;ls.set('rb.lang',lang)}
else if(ls.get('rb.lang')==='br')lang='br';
document.documentElement.lang=lang;

const BR={
/* en-tête, navigation */
"YOUL VAT - Régie Bénévoles":"YOUL VAT - Merañ ar skoazellerien",
"Régie Bénévoles":"Merañ ar skoazellerien",
"Inscription bénévoles":"Enskrivadur ar skoazellerien",
"Tous les événements":"An holl zarvoudoù",
"Aucun événement":"Darvoud ebet",
"Événement":"Darvoud",
"Événements":"Darvoudoù",
"Choisissez un événement":"Dibabit un darvoud",
"Chargement…":"O kargañ…",
"Chargement des données…":"O kargañ ar roadennoù…",
"Chargement des créneaux…":"O kargañ ar prantadoù…",
"Modifier":"Kemmañ",
"+ Événement":"+ Darvoud",
"S’inscrire":"En em enskrivañ",
"Organisateurs":"Aozerien",
"Déconnexion":"Digevreañ",
"Tableau de bord":"Taolenn-stur",
"Planning":"Steuñv",
"Bénévoles":"Skoazellerien",
"Postes & créneaux":"Postoù & prantadoù",
"Voir le planning":"Gwelet ar steuñv",

/* liste des événements */
"Cet événement n’existe pas ou a été supprimé. Choisissez-en un ci-dessous.":"N'eus ket eus an darvoud-se, pe dilamet eo bet. Dibabit unan amañ dindan.",
"Cet événement n’existe pas ou a été supprimé.":"N'eus ket eus an darvoud-se, pe dilamet eo bet.",
"{n} créneau":"{n} prantad",
"{n} créneaux":"{n} prantad",
"{f}/{n} places pourvues":"{f}/{n} plas leuniet",
"terminé":"echu",
"Aucun créneau pour l’instant.":"Prantad ebet evit ar mare.",
"Aucun planning publié pour l’instant.":"Steuñv ebet embannet evit ar mare.",
"Commencez par créer votre festival : dates, lieu, puis vos postes et créneaux.":"Krogit dre grouiñ ho kouel : deiziadoù, lec'h, ha goude ho postoù hag ho prantadoù.",
"Choisir mes créneaux":"Dibab ma frantadoù",
"Aucune inscription ouverte pour l’instant.":"Enskrivadur digor ebet evit ar mare.",

/* bandeau d'appel */
"Envie de donner un coup de main ?":"C'hoant ho peus da reiñ un taol-dorn ?",
"Choisissez un créneau libre, il suffit de votre prénom et de votre nom.":"Dibabit ur prantad dieub : ho anv-bihan hag hoc'h anv-familh a-walc'h.",
"S’inscrire comme bénévole":"En em enskrivañ evel skoazeller",

/* tableau de bord */
"Remplissage":"Leuniadur",
"{f} places pourvues sur {n}":"{f} plas leuniet war {n}",
"Places à pourvoir":"Plasoù da leuniañ",
"{n} créneau incomplet":"{n} prantad diglok",
"{n} créneaux incomplets":"{n} prantad diglok",
"Bénévoles mobilisés":"Skoazellerien engouestlet",
"sur {n} dans l’annuaire":"war {n} er roll",
"Heures planifiées":"Eurioù steuñvet",
"{h} par bénévole en moyenne":"{h} dre skoazeller e keidenn",
"{n} bénévole a des créneaux qui se chevauchent :":"Prantadoù d'ar memes eur evit {n} skoazeller :",
"{n} bénévoles ont des créneaux qui se chevauchent :":"Prantadoù d'ar memes eur evit {n} skoazeller :",
"manque {n}":"{n} a vank",
"+ {n} autres créneaux incomplets":"+ {n} prantad diglok all",
"Tous les créneaux sont pourvus.":"Leuniet eo an holl brantadoù.",
"Aucun créneau pour l’instant. Ajoutez des postes et créneaux.":"Prantad ebet evit ar mare. Ouzhpennit postoù ha prantadoù.",
"Aucun poste.":"Post ebet.",
"Contact bénévoles":"Darempred ar skoazellerien",
"À pourvoir en priorité":"Da leuniañ da gentañ",
"par ordre chronologique":"dre urzh an amzer",
"Par poste":"Dre bost",
"Par jour":"Dre zeiz",

/* planning */
"Définissez les dates de l’événement pour afficher le planning.":"Termenit deiziadoù an darvoud evit diskouez ar steuñv.",
"Créez d’abord des postes (bar, accueil, scène…) dans l’onglet Postes & créneaux.":"Krouit postoù da gentañ (tavarn, degemer, leurenn…) en ivinell Postoù & prantadoù.",
"Aucun créneau le {d}.":"Prantad ebet : {d}.",
"Poste":"Post",
"complet":"leun",
"partiel":"darn",
"vide":"goullo",
"Un créneau qui commence avant 6h compte dans la nuit du jour affiché (ex. 00h–05h le 31/12 = nuit du 31 au 1er).":"Ur prantad a grog a-raok 6h a gont evit noz an deiz diskouezet (sk. 00h–05h d'an 31/12 = noz an 31 d'ar 1añ).",
"Un créneau qui commence avant 6h compte dans la nuit du jour affiché.":"Ur prantad a grog a-raok 6h a gont evit noz an deiz diskouezet.",
"Affichage":"Diskouez",
"Grille":"Kael",
"Liste":"Roll",
"Supprimer les créneaux du jour":"Dilemel prantadoù an deiz",
"+ Créneau":"+ Prantad",
"Resp.":"Karg.",
"resp.":"karg.",

/* bénévoles */
"chevauchement":"memes eur",
"affecté":"lakaet",
"disponible":"dieub",
"Annuaire":"Roll ar skoazellerien",
"Rechercher":"Klask",
"Nom, compétence…":"Anv, barregezh…",
"Nom, téléphone, compétence…":"Anv, pellgomz, barregezh…",
"Exporter CSV":"Ezporzhiañ CSV",
"Sauvegarde JSON":"Gwarez JSON",
"+ Bénévole":"+ Skoazeller",
"Bénévole":"Skoazeller",
"Contact":"Darempred",
"Créneaux":"Prantadoù",
"Heures":"Eurioù",
"T-shirt":"T-shirt",
"État":"Stad",
"Aucun bénévole ne correspond à la recherche.":"Skoazeller ebet ne glot gant ar c'hlask.",
"Aucun bénévole. Ajoutez votre équipe.":"Skoazeller ebet. Ouzhpennit ho skipailh.",
"Créneaux et heures comptés pour l’événement sélectionné ; les absents ne sont pas comptés.":"Prantadoù hag eurioù jedet evit an darvoud dibabet ; n'eo ket jedet an dud ezvezant.",
"Bénévole supprimé":"Skoazeller dilamet",

/* postes */
"Aucun créneau.":"Prantad ebet.",
"+ Poste":"+ Post",
"Aucun poste. Créez par exemple Bar, Accueil billetterie, Catering artistes, Parking…":"Post ebet. Krouit da skouer Tavarn, Degemer ha bilhedoù, Boued an arzourien, Parklec'h…",
"Poste supprimé":"Post dilamet",

/* tiroirs */
"Fermer":"Serriñ",
"Espace organisateurs":"Tachenn an aozerien",
"Un mot de passe partagé pour toute l’équipe d’organisation.":"Ur ger-tremen boutin evit skipailh an aozerien a-bezh.",
"Mot de passe":"Ger-tremen",
"Se connecter":"Kevreañ",
"Aucun mot de passe administrateur n’est configuré. Renseignez admin_password_hash dans config.php.":"N'eus ger-tremen merour ebet. Leuniit admin_password_hash e config.php.",
"Statut":"Statud",
"Prévu":"Rakwelet",
"Présent":"Bezant",
"Absent":"Ezvezant",
"Retirer":"Tennañ",
"déjà occupé":"ac'hubet dija",
"{n}/{b} bénévoles":"{n}/{b} skoazeller",
"Équipe":"Skipailh",
"Personne n’est encore affecté.":"Den ebet c'hoazh.",
"S’inscrire sur ce créneau ({n} place)":"En em enskrivañ war ar prantad-mañ ({n} plas)",
"S’inscrire sur ce créneau ({n} places)":"En em enskrivañ war ar prantad-mañ ({n} plas)",
"Ce créneau est complet.":"Leun eo ar prantad-mañ.",
"Affecter un bénévole":"Lakaat ur skoazeller",
"Choisir…":"Dibab…",
"Affecter":"Lakaat",
"Triés par heures déjà planifiées (les moins chargés d’abord). Les bénévoles déjà occupés sur cette plage sont grisés.":"Renket hervez an eurioù steuñvet dija (ar re nebeutañ karget da gentañ). Louedet eo ar skoazellerien ac'hubet dija d'an eur-se.",
"Tous les bénévoles de l’annuaire sont déjà sur ce créneau.":"Emañ holl skoazellerien ar roll war ar prantad-mañ dija.",
"Modifier le créneau":"Kemmañ ar prantad",
"Supprimer le créneau":"Dilemel ar prantad",
"Téléphone":"Pellgomz",
"E-mail":"Postel",
"{h} sur cet événement":"{h} war an darvoud-mañ",
"Notes":"Notennoù",
"Créneaux sur {e}":"Prantadoù e {e}",
"l’événement":"an darvoud",
"Aucun créneau pour l’instant. Ouvrez un créneau dans le Planning pour l’affecter.":"Prantad ebet evit ar mare. Digorit ur prantad er steuñv evit e lakaat.",
"Modifier la fiche":"Kemmañ ar fichenn",
"Copier son planning":"Eilañ e steuñv",

/* formulaires */
"Nouvel événement":"Darvoud nevez",
"Modifier l’événement":"Kemmañ an darvoud",
"Événement créé":"Darvoud krouet",
"Nom de l’événement":"Anv an darvoud",
"Lieu":"Lec'h",
"Ville, site":"Kêr, lec'h",
"Premier jour":"Deiz kentañ",
"Dernier jour":"Deiz diwezhañ",
"Image / logo (adresse https)":"Skeudenn / logo (chomlec'h https)",
"adresse e-mail":"chomlec'h postel",
"Déroulé (une étape par ligne, ex. « 20:00 Ouverture des portes »)":"Program (ur bazenn dre linenn, sk. « 20:00 Digeriñ an dorioù »)",
"Nouveau poste":"Post nevez",
"Modifier le poste":"Kemmañ ar post",
"Poste ajouté":"Post ouzhpennet",
"Nom du poste":"Anv ar post",
"Bar principal":"Tavarn bennañ",
"Responsable":"Karget",
"Prénom Nom":"Anv-bihan Anv-familh",
"Couleur":"Liv",
"Consignes":"Kemennoù",
"Tenue, matériel, point de rendez-vous…":"Dilhad, dafar, lec'h emgav…",
"Nouveau créneau":"Prantad nevez",
"Créneau ajouté":"Prantad ouzhpennet",
"Jour (nuit comprise jusqu’à 6h)":"Deiz (an noz betek 6h e-barzh)",
"Début":"Deroù",
"Fin":"Dibenn",
"Bénévoles nécessaires":"Niver a skoazellerien ret",
"Note":"Notenn",
"Ex. : briefing 30 min avant":"Sk. : emvod 30 min a-raok",
"Nouveau bénévole":"Skoazeller nevez",
"Bénévole ajouté":"Skoazeller ouzhpennet",
"Prénom":"Anv-bihan",
"Nom":"Anv-familh",
"Téléphone et e-mail sont facultatifs mais recommandés : c’est plus simple pour vous prévenir d’un changement. Ils ne sont visibles que par les organisateurs.":"N'eo ket ret ar pellgomz hag ar postel, met erbedet int : aesoc'h eo evit ho kelaouiñ ma vez ur cheñchamant. N'int gwelet nemet gant an aozerien.",
"Taille de T-shirt":"Ment an T-shirt",
"Compétences":"Barregezhioù",
"Permis B, PSC1, anglais, caisse…":"Aotre-bleinañ B, PSC1, saozneg, kef…",
"Disponibilités, contraintes, allergies…":"Pegoulz e vezit dieub, rediennoù, alergiezhoù…",
"Bleu":"Glas","Corail":"Koural","Vert":"Gwer","Violet":"Mouk","Moutarde":"Sezv","Rose":"Roz","Cyan":"Glaswer","Olive":"Olivez",
"La suppression retire aussi ses postes, créneaux et affectations.":"Dilamet e vo ivez e bostoù, e brantadoù hag e enskrivadurioù.",
"La suppression retire aussi ses créneaux et leurs affectations.":"Dilamet e vo ivez e brantadoù hag o enskrivadurioù.",
"La suppression retire aussi les affectations de ce créneau.":"Dilamet e vo ivez enskrivadurioù ar prantad-mañ.",
"Créez d’abord un poste.":"Krouit ur post da gentañ.",
"Enregistrer":"Enrollañ",
"Créer":"Krouiñ",
"Supprimer":"Dilemel",
"À compléter : {l}":"Da leuniañ : {l}",
"Modifications enregistrées":"Kemmoù enrollet",

/* confirmations, messages */
"Confirmer":"Kadarnaat",
"Confirmer ?":"Kadarnaat ?",
"Confirmer la suppression":"Kadarnaat an dilemel",
"Confirmer (retire {n} affectation)":"Kadarnaat (tennañ {n} enskrivadur)",
"Confirmer (retire {n} affectations)":"Kadarnaat (tennañ {n} enskrivadur)",
"Confirmer : {c} et {a} supprimés":"Kadarnaat : dilemel {c} ha {a}",
"Confirmer : {c} supprimés":"Kadarnaat : dilemel {c}",
"{n} affectation":"{n} enskrivadur",
"{n} affectations":"{n} enskrivadur",
"Créneaux du {d} supprimés":"Dilamet eo bet prantadoù {d}",
"Créneau supprimé":"Prantad dilamet",
"Supprimé":"Dilamet",
"Planning copié, prêt à envoyer par SMS ou e-mail.":"Steuñv eilet, prest da vezañ kaset dre SMS pe dre bostel.",
"Planning copié.":"Steuñv eilet.",
"Copie impossible dans cette vue.":"N'haller ket eilañ amañ.",
"Copie impossible sur cet appareil.":"N'haller ket eilañ war an ardivink-mañ.",
"Choisissez un bénévole.":"Dibabit ur skoazeller.",
"{b} affecté":"{b} lakaet",
"Retiré du créneau":"Tennet eus ar prantad",
"Statut mis à jour":"Statud hizivaet",
"Connexion impossible.":"N'haller ket kevreañ.",
"Chargement impossible.":"N'haller ket kargañ.",
"Serveur injoignable. Vérifiez la connexion.":"N'haller ket tizhout ar servijer. Gwiriit ar gevreadenn.",
"Serveur injoignable. Vérifiez votre connexion.":"N'haller ket tizhout ar servijer. Gwiriit ho kevreadenn.",
"La base de données n’est pas disponible.":"N'eo ket hegerz ar stlennvon.",
"Session expirée.":"Echu eo an dalc'h.",
"Enregistrement impossible pour le moment. Réessayez.":"N'haller ket enrollañ evit ar mare. Klaskit en-dro.",
"Impossible de charger les données. Vérifiez la connexion puis rechargez la page.":"N'haller ket kargañ ar roadennoù. Gwiriit ar gevreadenn hag adkargit ar bajenn.",

/* page d'inscription */
"Devenir bénévole":"Bezañ skoazeller",
"Choisissez un ou plusieurs créneaux libres : il suffit de votre prénom et de votre nom.":"Dibabit ur prantad dieub pe meur a hini : ho anv-bihan hag hoc'h anv-familh a-walc'h.",
"{n} créneau à pourvoir":"{n} prantad da leuniañ",
"{n} créneaux à pourvoir":"{n} prantad da leuniañ",
"{n} place libre":"{n} plas vak",
"{n} places libres":"{n} plas vak",
"Vous êtes":"Piv oc'h ?",
"prenom@exemple.bzh":"anv@skouer.bzh",
"Vos prénom et nom apparaissent sur le planning public.":"Hoc'h anv-bihan hag hoc'h anv-familh a vo gwelet war ar steuñv foran.",
"Mes créneaux":"Ma frantadoù",
"Copier":"Eilañ",
"Un empêchement ? Prévenez l’organisation":"Un harz ? Kelaouit an aozerien",
"Tous":"An holl",
"Tous les postes":"An holl bostoù",
"Afficher les créneaux complets":"Diskouez ar prantadoù leun",
"Inscrit·e ✓":"Enskrivet ✓",
"Complet":"Leun",
"Déjà pris·e":"Ac'hubet dija",
"Je m’inscris":"En em enskrivañ",
"Aucun créneau libre avec ces filtres.":"Prantad dieub ebet gant ar siloù-mañ.",
"Impossible de charger les créneaux. Réessayez dans un instant.":"N'haller ket kargañ ar prantadoù. Klaskit en-dro a-benn ur pennadig.",
"Indiquez d’abord votre prénom et votre nom.":"Roit hoc'h anv-bihan hag hoc'h anv-familh da gentañ.",
"Merci {p} ! Inscrit·e : {poste}, {quand}.":"Trugarez {p} ! Enskrivet : {poste}, {quand}.",
"Inscription impossible. Réessayez.":"N'haller ket en em enskrivañ. Klaskit en-dro.",

/* page contact */
"Nous contacter":"Mont e darempred ganeomp",
"Une question sur le bénévolat ? Écrivez-nous ou appelez-nous.":"Ur goulenn diwar-benn ar skoazell ? Skrivit pe pellgomzit deomp.",
"Autres informations":"Titouroù all",
"Aucune information de contact pour l’instant.":"Titour darempred ebet evit ar mare.",
"Voir les coordonnées":"Gwelet an titouroù darempred",
"E-mail de contact":"Postel darempred",
"Téléphone de contact":"Pellgomz darempred",
"Autres informations de contact (une par ligne : adresse, site web, réseaux sociaux…)":"Titouroù darempred all (unan dre linenn : chomlec'h, lec'hienn web, rouedadoù sokial…)",

/* éditeur de la page contact */
"Modifier la page":"Kemmañ ar bajenn",
"Gras":"Tev",
"Italique":"Stouet",
"Titre":"Titl",
"Liste à puces":"Roll gant pikoù",
"Liste numérotée":"Roll niverennet",
"Lien":"Liamm",
"Retirer le lien":"Tennañ al liamm",
"Effacer la mise en forme":"Diverkañ ar stumm",
"Mise en forme":"Stummañ",
"Texte de la page en {l}":"Testenn ar bajenn e {l}",
"breton":"brezhoneg",
"français":"galleg",
"Pour modifier l’autre version, changez de langue en haut de page (après avoir enregistré).":"Evit kemmañ ar stumm all, cheñchit yezh e penn ar bajenn (goude bezañ enrollet).",
"Coordonnées":"Titouroù darempred",
"Annuler":"Nullañ",
"Adresse du lien (https://…, mailto:… ou tel:…)":"Chomlec'h al liamm (https://…, mailto:… pe tel:…)",
"Adresse de lien invalide : utilisez https://, mailto: ou tel:":"Chomlec'h liamm direizh : implijit https://, mailto: pe tel:",
"Texte trop long.":"Re hir eo an destenn.",

/* messages du serveur (api.php, lib/db.php) */
"Action inconnue.":"Ober dianav.",
"Adresse e-mail invalide.":"Chomlec'h postel direizh.",
"Aucun mot de passe administrateur n'est configuré (voir config.php).":"N'eus ger-tremen merour ebet (gwelet config.php).",
"Ce bénévole est déjà sur ce créneau.":"Emañ ar skoazeller-se war ar prantad-mañ dija.",
"Ce créneau est complet. Choisissez-en un autre.":"Leun eo ar prantad-mañ. Dibabit unan all.",
"Ce créneau n’existe plus.":"N'eus ket eus ar prantad-mañ ken.",
"Couleur invalide.":"Liv direizh.",
"Erreur de base de données.":"Fazi er stlennvon.",
"Identifiant manquant.":"Mankout a ra an anaouder.",
"Il faut au moins 1 bénévole.":"Ezhomm ez eus eus 1 skoazeller d'an nebeutañ.",
"Indiquez un prénom et un nom valides (60 caractères max).":"Roit un anv-bihan hag un anv-familh reizh (60 arouezenn d'ar muiañ).",
"JSON invalide.":"JSON direizh.",
"Jour invalide.":"Deiz direizh.",
"L'adresse de l'image doit commencer par https://":"Ret eo da chomlec'h ar skeudenn kregiñ gant https://",
"Le dernier jour doit suivre le premier.":"Ret eo d'an deiz diwezhañ dont goude an hini kentañ.",
"Le début et la fin doivent être différents.":"Ret eo d'an deroù ha d'an dibenn bezañ disheñvel.",
"Le lien du Google Sheet doit commencer par https://":"Ret eo da liamm ar Google Sheet kregiñ gant https://",
"Mot de passe incorrect.":"Ger-tremen fall.",
"Numéro de téléphone invalide.":"Niverenn bellgomz direizh.",
"Rien à modifier.":"Netra da gemmañ.",
"Réservé aux organisateurs : connectez-vous.":"Miret evit an aozerien : kevreit.",
"Session expirée : rechargez la page.":"Echu eo an dalc'h : adkargit ar bajenn.",
"Statut invalide.":"Statud direizh.",
"Table non autorisée.":"Taolenn difennet.",
"Taille invalide.":"Ment direizh.",
"Trop d’inscriptions depuis cet appareil. Contactez l’organisation.":"Re a enskrivadurioù diouzh an ardivink-mañ. Kit e darempred gant an aozerien.",
"Valeur refusée par la base.":"Talvoud nac'het gant ar stlennvon.",
"Vous êtes déjà inscrit·e sur ce créneau.":"Enskrivet oc'h dija war ar prantad-mañ.",
"Vous êtes déjà inscrit·e à la même heure : {poste} ({debut}–{fin}).":"Enskrivet oc'h dija d'ar memes eur : {poste} ({debut}–{fin}).",
"Élément introuvable (supprimé entre-temps ?).":"N'eo ket bet kavet an elfenn (dilamet e-keit-se ?).",
"Élément lié introuvable (supprimé entre-temps ?).":"N'eo ket bet kavet an elfenn liammet (dilamet e-keit-se ?).",
"L'extension PHP pdo_sqlite n'est pas activée sur cet hébergement.":"N'eo ket gweredekaet an astenn PHP pdo_sqlite war an herberc'hiañ-mañ.",
"Impossible de créer le dossier data/. Créez-le et rendez-le inscriptible (chmod 775).":"N'haller ket krouiñ ar c'havlec'h data/. Krouit anezhañ ha roit an aotre skrivañ dezhañ (chmod 775).",
"Le dossier data/ n'est pas inscriptible par PHP. Donnez-lui les droits d'écriture (chmod 775 ou 777 selon l'hébergeur).":"N'hall ket PHP skrivañ er c'havlec'h data/. Roit dezhañ an aotreoù skrivañ (chmod 775 pe 777 hervez an herberc'hier)."
};

function fill(s,vars){return vars?s.replace(/\{(\w+)\}/g,(m,k)=>vars[k]!=null?vars[k]:m):s}
function t(s,vars){return fill(lang==='br'&&BR[s]!=null?BR[s]:s,vars)}
function tn(n,one,many,vars){return t(n>1?many:one,Object.assign({n},vars||{}))}

/* dates : Intl pour le français ; noms bretons écrits à la main (les navigateurs ne connaissent pas tous « br ») */
const BR_D=['Sul','Lun','Meurzh',"Merc'her",'Yaou','Gwener','Sadorn'];
const BR_DS=['Sul','Lun','Meu.','Mer.','Yaou','Gwe.','Sad.'];
const BR_M=['Genver',"C'hwevrer",'Meurzh','Ebrel','Mae','Mezheven','Gouere','Eost','Gwengolo','Here','Du','Kerzu'];
const BR_MS=['Gen.',"C'hwe.",'Meur.','Ebr.','Mae','Mezh.','Goue.','Eost','Gwen.','Here','Du','Kzu.'];
function fmtDay(j,o){
  o=o||{weekday:'long',day:'numeric',month:'long'};
  const d=new Date(j+'T12:00:00');if(isNaN(d))return j;
  if(lang!=='br'){try{return d.toLocaleDateString('fr-FR',o)}catch(e){return j}}
  const p=[];
  if(o.weekday)p.push((o.weekday==='short'?BR_DS:BR_D)[d.getDay()]);
  if(o.day)p.push(d.getDate());
  if(o.month)p.push((o.month==='short'?BR_MS:BR_M)[d.getMonth()]);
  if(o.year)p.push(d.getFullYear());
  return p.join(' ');
}

/* textes fixes du HTML : data-i18n="clé" (contenu) et data-i18n-title="clé" (infobulle) ; + sélecteur de langue */
function applyStatic(){
  document.querySelectorAll('[data-i18n]').forEach(el=>{el.textContent=t(el.getAttribute('data-i18n'))});
  document.querySelectorAll('[data-i18n-title]').forEach(el=>{el.title=t(el.getAttribute('data-i18n-title'))});
  document.querySelectorAll('.langsw').forEach(box=>{
    box.innerHTML=LANGS.map(([k,long,short])=>`<button type="button" data-lang="${k}" aria-pressed="${k===lang}" title="${long}" lang="${k}"><span class="l-long">${long}</span><span class="l-short">${short}</span></button>`).join('');
  });
}
function setLang(l){
  if(l===lang)return;
  ls.set('rb.lang',l);
  const u=new URL(location.href);u.searchParams.delete('lang'); /* la préférence mémorisée suffit */
  location.href=u.toString();
}
document.addEventListener('click',e=>{const b=e.target.closest('[data-lang]');if(b)setLang(b.dataset.lang)});

window.I18N={get lang(){return lang},t,tn,fmtDay,applyStatic,setLang};
})();
