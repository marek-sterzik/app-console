# SPSOstrov GetOpt

This is a PHP getopt library with these design goals:

* Clean option parsing with compatibility very close to standard posix getopt library.
* Easy option definitions. Each option type may be defined with a single string. The whole configuration is just an array of strings.
* Support for option checks, array processing and other cool staff.

## Usage

Example:

```php
use SPSOstrov\AppConsole\GetOpt\Options;

$options = new Options([
    'r|regular-option                  This is a regular option',
    'a|option-with-argument:           This is an option with an argument',
    'o|option-with-optional-argument?  This is an option with an optional argument'
]);

$args = ["-r", "-a", "argument", "-o"];

$parsed = $options->parseArgs($args);
```

## Option definition

Each option string consist of two basic parts: The option definition and the human readable description. The option definition cannot contain spaces (except if quoted), the human readable description
starts with a first space.

The option definition contains these parts:

* list of options
* optional type specification
* optional quantity specification
* optional write rules
* optional argument checker

### List of option

Each option needs to be specified by a number of options separated by `|`. For example:

```
o|option|simple-option
```

There are also some special option names:

* `@` - represents all short options not explicitely defined
* `@@` - represents all long options not explicitely defined
* `@@@` - represents all short and long options not explicitely defined
* `$` - special option representing regular arguments (cannot be combined with other option names)

### Type specification

### Quantity specification

### Write rules

### Argument checker

Specify 
f|force
O|out-file:
f|force Force to override
O|out-file:=file
red[color=red]
green[color=green]
blue[color=blue]
i|input*[in]=file
i|input*=file[in]
@|@@{1,3}
${1,3}
$?
$:
$:[x]

@ - all short options
@@ - all long options
@@@ - short and long options
| - option separator
: - equivalent to {1}
{1} - equivalent to {1,1}
{a,b} - from a to b repetitions
{a,} - from a to infinity
? - equivalent to {0,1}
* - equivalent to {0,}
[in] - write value to "in"
[in=x] - write value "x" to "in"
[in=x,out=y] - write value "x" to "in" and "y" to "out"
[in=$] - write value of the option to "in"
[out=$in] - write the value currently stored in "in" to "out"
[@] - write value to all short options
[@@] - write value to all long options
[@@@] - write value to all options [default]
=file - apply checker "file"
$ - arguments (may be used multiple times, cannot be combined with others)

parsed1:
    short: ["f"]
    long: ["force"]
    hasArgument: false
    min: 0
    max: null
    checker: null
    writes:
        - from: '$'
          to: in
        - out
    description: null
parsed2:
    short: ["f"]
    long: ["force"]
    hasArgument: true
    min: 0
    max: null
    checker: null
    writes:
        - from: '$'
          to: in
        - from: '$'
          to: out
parsed3-argument:
    short: null
    long: null
    hasArgument: true
    min: 0
    max: null
    checker: null
    writes:
        - from: '$'
          to: '@@'
        - from: '$'
          to: '@'
