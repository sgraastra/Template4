# Template4 Syntax

- [Lexical-grammar](#lexical-grammar)
- [Language-grammar](#language-grammar)

Below is a simplified version of the Jison-grammar from
[studyportals/template4-parser](https://github.com/studyportals/template4-parser).

The grammar has been modified for readability &ndash; this is not functional
Jison-grammar!

## Lexical-grammar

```javascript
%lex

%options case-insensitive easy_keyword_rules

%x tp4
%x tp4str

%%
              "<!--"\s*"[{"|"[{" this.pushState('tp4'); return 'TP4_OPEN';
/*<tp4>*/     "var"|"replace"    return 'TP4_VAR';
/*<tp4>*/     "if"|"condition"   return 'TP4_IF';
/*<tp4>*/     "section"          return 'TP4_SECTION';
/*<tp4>*/     "loop"|"repeater"  return 'TP4_LOOP';
/*<tp4>*/     "include"          return 'TP4_INCLUDE';
/*<tp4>*/     "in"               return 'TP4_IN';
/*<tp4>*/     "is"               return 'TP4_IS';
/*<tp4>*/     "not"|"!"          return 'TP4_NOT';
/*<tp4>*/     "end"              return 'TP4_END';
/*<tp4>*/     "template"         return 'TP4_TEMPLATE';
/*<tp4>*/     "as"               return 'TP4_AS';
/*<tp4>*/     "raw"              return 'TP4_RAW';
/*<tp4>*/     "\""               this.pushState('tp4str'); return 'TP4_QUOTE';
/*<tp4str>*/  "[^\"\n]"          return 'TP4_STRING';
/*<tp4str>*/  "\n+"              return 'TP4_LF_IN_STRING';
/*<tp4str>*/  "\""               this.popState(); return 'TP4_QUOTE';
/*<tp4>*/     "[a-z0-9_]+"       return 'TP4_VALUE';
/*<tp4>*/     "[\s]+"            // Ignore whitespace inside TP4-syntax
/*<tp4>*/     "}]"\s*"-->"|"}]"  this.popState(); return 'TP4_CLOSE';
/*<tp4>*/     "[{"               return 'TP4_OPEN'      // Disallow dangling TP4_OPEN
              "}]"               return 'TP4_CLOSE'     // Disallow dangling TP4_CLOSE
              "[{\[<]+"          return 'CONTROL_CHARS'
              "[^{\[<]+"         return 'HTML'
              <<EOF>>            return 'EOF'

/lex
```

## Language-grammar

```javascript
%ebnf
%start file

%%

file: template EOF
;

template: // empty, or
          | template part
;

part:   HTML
      | CONTROL_CHARS
      | TP4_OPEN TP4_VAR TP4_VALUE[name] TP4_RAW?[raw] TP4_CLOSE
        // [{var … }]
      | TP4_OPEN TP4_IF TP4_VALUE[name1] tp4_operator (TP4_VALUE|tp4_string)[compare] TP4_CLOSE
          template
        TP4_OPEN TP4_IF TP4_VALUE?[name2] TP4_END TP4_CLOSE
        // [{if … is|!is … }] … [{if end}]
      | TP4_OPEN TP4_IF TP4_VALUE[name1] tp4_set_operator (TP4_VALUE|tp4_string)+[compare] TP4_CLOSE
          template
        TP4_OPEN TP4_IF TP4_VALUE?[name2] TP4_END TP4_CLOSE
        // [{if … in|!in … }] … [{if end}]
      | TP4_OPEN TP4_SECTION TP4_VALUE[name1] TP4_CLOSE
          template
        TP4_OPEN TP4_SECTION TP4_VALUE?[name2] TP4_END TP4_CLOSE
        // [{section … }] … [{section end}]
      | TP4_OPEN TP4_LOOP TP4_VALUE[name1] TP4_CLOSE
          template
        TP4_OPEN TP4_LOOP TP4_VALUE?[name2] TP4_END TP4_CLOSE
        // [{loop … }] … [{loop end}]
      | TP4_OPEN TP4_INCLUDE tp4_string TP4_CLOSE
        // [{include … }]
      | TP4_OPEN TP4_INCLUDE TP4_TEMPLATE tp4_string tp4_as_name TP4_CLOSE
        // [{include template … }]
;

tp4_string:   TP4_QUOTE TP4_QUOTE
              // "" (i.e. empty string)
            | TP4_QUOTE TP4_STRING TP4_QUOTE
              // "…"
;

tp4_operator:   TP4_IS
              | TP4_NOT
              | TP4_NOT TP4_IS
;

tp4_set_operator:   TP4_IN
                  | TP4_NOT TP4_IN
;

tp4_as_name:  // empty, or
              | TP4_AS (TP4_VALUE|tp4_string)[value]
;

%%
```
