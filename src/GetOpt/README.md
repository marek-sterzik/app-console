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
    'a|option-with-param:           This is an option with an parameter',
    'o|option-with-optional-param?  This is an option with an optional parameter'
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
* optional parameter checker

### List of option

Each option needs to be specified by a number of options separated by `|`. For example:

```
o|option|simple-option
```

There are also special option names:

* `@` - represents all short options not explicitely defined
* `@@` - represents all long options not explicitely defined
* `@@@` - represents all short and long options not explicitely defined
* if the list starts with `$` the option will be bound to unnamed arguments instead of named options

Handling unnamed arguments:

```
$command  command
$args*     command arguments
```

This will store the first unnamed argument to varable `command` and all other variables in variable `args`.

### Type specification

There are 3 types of 

```
option-without-param    Option without parameter (may still hava a parameter, if quantity is specified)
option-without-param~   Option without parameter (explicitly set)
option-required-param:  Option with required parameter
option-optional-param?  Option with optional parameter
option-array-param*     Option with multiple parameters
$argument               Regular single argument
$optional-argument?     Zero or one single argument
$array-argument*        An array of arguments
```

### Quantity specification

The quantity specifies how many occurences of a single option may occur. It is set automatically depending on a type,
but you may explicitely set it:

```
option{3}    Exactly 3 options
option{1,5}  From 1 to 5 options
option{4,}   From 4 to infinity options
option{,5}   From 0 to 5 options
option{,}    From 0 to infinity options
```

Note that quantity and option type are completely disconnected options. On the other hand if one of the information is not specified,
it is calculated from the other. If both information are missing, first the option type is set to _no parameter_ and then the quantity `{0,1}` is assigned.

Default quantities:

```
option~   Default quantity is {0,1}
option:   Default quantity is {0,1}
option?   Default quantity is {0,1}
option*   Default quantity is {0,}
$arg~     Makes no sense, but is allowed and default quantity is {0,0}
$arg:     Default quantity is {1,1}
$arg?     Default quantity is {0,1} (otherwise arg acts exactly the same as $arg:)
$arg*     Default quantity is {0,}
```

Default type is calculated from the quantity according to these rules:

* If the quantity is not specified, option defaults to `~`.
* If the quantity is not specified, argument defaults to `:`.
* If maximum quantity is greater than 1 (or infinity), option and arguments defaults to `*`.
* All other cases defaults to `~`.


### Write rules

Options and arguments are received from the command line one by one. Each argument is processed according to some write rules. Write rules specifies, what should happen when a single option
is processed. There is a _variable registry_ (i.e. associative array) available for each write. Custom operations may be defined. For example:

```
option:[opt]                 Write the value into the variable `opt` instead of variable `option`.
option:[option,op,x]         Three custom rules. First write the value to variable `option` then write to `op` and last write to `x`
option[option="abc"]         Write the string `abc` into variable `option` when --option is specified.
option[option=op]            Write the value of the variable `op` into variable `option` when --option is specified.
option:[opt=$]               Write the value of the option parameter into the variable `opt`. Same as `option[opt]`
o|option:[@=$]               Write the value of the option parameter into all variables named as short options (i.e. write it into variable `o`).
o|option:[@@=$]              Write the value of the option parameter into all variables named as long options (i.e. write it into variable `option`).
o|option:[@@@=$]             Write the value of the option parameter into all variables (i.e. write it into `o` and `option). This is the default rule if no other is specified.
option[opt=@]                Write the name of the really used option into the variable `opt`.
option[opt=@@]               Same as above.
option[opt=@@@]              Write the list of all options (separated by `|`) into the variable `opt`.
yes|no[enabled=@]            Easy boolean implementation with possibility to keep default state.
yes|no{1}[enabled=@]         Easy boolean implementation with possibility where explicitely setting one option is required.
low|medium|high{1}[speed=@]  Easy tristate switch.
```

### Argument checker

You may also specify an argument checker for each option. It is not implemented in the current version, but the syntax already supports that feature:

```
option:=int   # Check the option as `int`.
```

Each checker needs to be defined as string. But the current implementation does not specify how checkers are assigned to a particular string.

