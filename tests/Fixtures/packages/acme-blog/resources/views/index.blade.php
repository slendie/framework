PACKAGE-VIEW @acmeName($who)
@acme
IN-ACME
@elseacme
OUT-ACME
@endacme
@acmerole('editor')
ROLE-EDITOR
@elseacmerole('admin')
ROLE-ADMIN
@else
ROLE-OTHER
@endacmerole
