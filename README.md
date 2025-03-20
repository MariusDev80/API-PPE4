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
