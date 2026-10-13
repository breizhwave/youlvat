-- Demo event loaded on a new install (config 'seed_file'). Small, in English, and covers the main cases:
-- several days, a night slot (starts before 06:00 = night of the given day), a slot past midnight (fin < debut).
BEGIN;
INSERT INTO events (id,nom,lieu,debut,fin,contact,contact_tel,contact_infos,deroule) VALUES ('demo','Summer Street Festival (demo)','Town square','2027-07-09','2027-07-11','volunteers@example.org','+44 20 7946 0000','https://example.org','15:00 Kids activities
18:00 Doors open
19:00 First band
23:00 Headliner
01:30 Last orders
02:00 Close');
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('setup','demo','Setup & teardown',0,NULL,'Stage, tables, signs. Bring gloves.');
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('tickets','demo','Tickets & welcome',2,NULL,'Check tickets, hand out wristbands, answer questions.');
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('bar','demo','Bar',4,NULL,'Serve drinks, wash cups. Over 18 only.');
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('food','demo','Food stand',1,NULL,NULL);
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('cloak','demo','Cloakroom',6,NULL,NULL);
INSERT INTO postes (id,event_id,nom,couleur,responsable,description) VALUES ('kids','demo','Kids corner',5,NULL,'Games and face painting.');
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c01','demo','setup','2027-07-09','14:00','18:00',4,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c02','demo','setup','2027-07-10','09:00','13:00',4,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c03','demo','kids','2027-07-10','15:00','18:00',2,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c04','demo','tickets','2027-07-10','17:30','21:00',2,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c05','demo','tickets','2027-07-10','21:00','00:00',2,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c06','demo','bar','2027-07-10','18:00','21:00',3,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c07','demo','bar','2027-07-10','21:00','00:00',3,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c08','demo','bar','2027-07-10','00:00','02:00',2,'Night shift');
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c09','demo','food','2027-07-10','18:00','21:00',2,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c10','demo','food','2027-07-10','21:00','23:30',2,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c11','demo','cloak','2027-07-10','18:00','22:00',1,NULL);
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c12','demo','cloak','2027-07-10','22:00','02:00',1,'Until the last coat is collected');
INSERT INTO creneaux (id,event_id,poste_id,jour,debut,fin,besoin,note) VALUES ('demo-c13','demo','setup','2027-07-11','10:00','14:00',5,NULL);
COMMIT;
