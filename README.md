# ROUTES 

base : http://localhost/API-PPE4

## OBTENTION/VERIFICATION TOKEN

-> GET /login/{role}/{login}/{mdp}
-> GET /verifToken - Authorization Bearer + token

## AUTRES REQUETES

-> GET /{table}/all - Authorization Bearer + token
-> GET /{table}/{id} - Authorization Bearer + token

-> POST /{table}/add + données en JSON - Authorization Bearer + token

-> PUT /{table}/update/{id} + données en JSON - Authorization Bearer + token

-> DELETE /{table}/delete/{id} - Authorization Bearer + token

# CODES ERREURS

```
401 - Forbidden
403 - Unauthorized
404 - Not Found
500 - Internal Server Error
```

# ACCES

Un full access permet la visualisation et modification de toutes les données des tables concernées, un restricted access ne permet que la visualisation et modification des données qui concernent la personne authentifiée.

```
Admin -> full access, toutes les tables
Infirmiere en chef -> full access, tables : infirmiere, convalescence, patient, soins, soins_visite, visite, type_soins, temoignage
Infirmiere -> restricted access, tables : visite
Patient -> restricted access, tables : temoignages, visite
```