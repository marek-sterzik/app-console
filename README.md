# SPŠOstrov App Console

The App Console is an easy tool for multiple purposes allowing to define custom commands bound together with any composer-managed project.

Goals:

* Provide a way how to easily maintain different kinds of management scripts.
* Possibility to create scripts inside of the app console framework with full support for unix-style options, but without the necessity messing with getopt implementation.
* Support for a large scale of scripting languages. Support the full OS specific options.
* Plugin architecture where scripts of the app console may be defined not only in the project itself, but also in other composer dependencies.
* Don't provide any specific functions, but provide a **platform** where anybody may define own custom functionality.

## Getting started

If you want to use the app console, just use it as a dependency:
```bash
composer require spsostrov/app-console
```

The app console will provide a command in vendor binaries, which you may call:
```bash
vendor/bin/app <command> [command-arguments]
```

This binary fully supports unix-style options and is a PHP file. Therefore you may run it anywhere where a php interpreter exists. The interesting staff on the whole app console is, where and how all the commands of the console are loaded. They are in fact loaded from composer packages. The commands are loaded from these packages:

* From the root package
* From all packages of type `spsostrov-app-console`
* From the `spsostrov/app-console` itself. (Currently no commands are defined here but it may change in the future.)

If a command of the same name is defined in multiple packages, the first found command is used. The order of the packages exactly matches the schema given above. The order of `spsostrov-app-console` packges is now given by the order how composer is ordering them and it is not much reliable. Maybe in a future version of the app console clean rules defining the order could be implemented. It means, that any command is first looked for in the root package, then in packages of type `spsostrov-app-console` and in the `spsostrov/app-console` last.

Commands are searched in the `scripts/` subdirectory of the package unless stated otherwise. If you want to change the default search directory, you may explicitely set it up in `composer.json`:

```json
{
    "extra": {
        "spsostrov-app-console": {
            "scripts-dir": "some/other/directory/specified/relatively"
        }
    }
}
```

If you want to create an app console command, just create an executable binary inside of the `scripts/` directory. In such a case, the console will  pass the arguments one to one, without any parsing. Only if `--help` and/or `--version` is passed, it is recognized inside of the app console and handled internally not passing it to the app console command.

Console will just run the command as a regular system-wide executable. Windows platform is not (yet) supported, but it definitely works well under the WSL subsystem.

## The command manifest

The power of the app console comes with the fact, that you may extend the available information by provide a manifest file to each command. The manifest file has the same name as the executable, but has the extension `.json`. For example the manifest file for the command `run` has the name `run.json`.

The manifest file is just a json file, which may look like this:

```json
{
    "description": "Short description of the command",
    "help": "Long description of the command.",
    "options": [],
    "args": [],
    "hidden": false,
    "invoker-params": []
}
```

The meaning of the configuration records:

* `description` - short description of the command
* `help` - long description of the command
* `options` - option parsing rules (see the package [spsostrov/getopt](https://github.com/marek-sterzik/getopt) for details)
* `args` - rules how the parsed options may be converted back to executable script arguments. This allows to pass different arguments to the app console while the script is consumming them in a much easier way without the necessity messing with getopt. See later how the rules does look like.
* `hidden` - if set to true, the command will be available, but it will not be visible in the help. Also all commands which name starts with a dot are hidden by default.
* `invoker-params` - parameters for the invoker command (see later)

## Command argument processing

The App Console comes with an argument processing model, which may be set up very easily and is very powerful. The main idea is, that passing arguments from the app console to a command executable binary is done in these steps:

1. The App Console parses its global options and the command name itself. According to this it decides which command should be invoked, etc. Arguments after the command name are decided and later should be passed to the executable binary.
2. These arguments are processed using the `options` configuration in the manifest file (see above). The result of this processing is always a regular PHP associative array. See the package [spsostrov/getopt](https://github.com/marek-sterzik/getopt) for details.
3. From that associative array of parsed options arguments are reconstructed back, but they may be totally rearranged in a way so it is much easier to be processed by the script.

To use the full potential of the option parsing, the command must define either the `options` and also the `args` fields in the manifest file.

When only `options` are defined, but `args` not, the only advantage of this solution would be, that app console will properly look for the automatically defined `--help` and `--version` options inside of all other options since the default value of `args` causes to always pass the arguments exactly as they are.

### Defining args

The `args` field is just an array of strings containing rules what should be added in the argument list. The rules have access to the parsed options and therefore parsed options may be easily put into the final argument list.

These rules may be used:

* `$data` - put variable `data` from parsed options to the argument list as a single argument (scalar context)
* `@data` - put the array variable `data` from parsed options to the argument list as multiple arguments (array context)
* `#data` - put the number of arguments in the array `data`. If it is not an array, it still may be `0` or `1` depending on the fact if the value does exist or not (count context)
* `?data` - same as `$data` but don't put any argument if `data` does not exist. (optional scalar context)
* $data?"default-value" - put variable `data` in the argument list, but use default value `default-value` if variable `data` is not defined.

### Example

Let say we want to create a command taking any number of input files as arguments and having one output file argument defaulting to stdout.

The processing command is designed in a way that the output file is the first argument (defaulting to `-` if no output is specified) and all other arguments represent the input files.

We want to have the options designed in a way to be called:

```bash
vendor/bin/app command --output <output> [input1 [input2 ...]
```

To acheive this goal, you may put in the manifest file:
```json
{
    "options": [
        "o|output: Output file",
        "$input* Input file"
    ],
    "args": [
        "$output?\"-\"",
        "@input"
    ]
}
```

## Events

The App Console contain a mechanism not only to invoke particular commands, but also to process events of different kinds. The idea behind events is almost identical to commands with one difference: **Events are always invoked in all packages.**

Any command may be processed as an event. Nothing special is required. There is only a convention:

Event command names should always start with the dot, so that they are invisible by default. If you want to invoke a command as an event, just call:

```bash
vendor/bin/app --all .configure
```

In that case the `.configure` command is called from inside of all packages.

## Invoking command from a particular package

```
vendor/bin/app --package spsostrov/runtime dc
```

This will invoke the `dc` command from the package `spsostrov/runtime`.

## Using a custom command invoker

It is possible to set up a special scenario when commands are not invoked directly by executing them, but they are executed using an "invoker". The invoker takes the binary to be executed and its arguments as invoker's arguments and it may do whatever it want.

An invoker is a regular command defined in this way:

* the name of the command is always `.invoke`
* it contains the flag `"is-invoker": true` in its manifest file.

Then all commands are executed using such an invoker. The purpose of using an invoker depends on the needs, but the original goal was to provide a mechanism how to automatically do some rights escallation before invoking any command.

### Invoker parameters

An invoker may be also parametrized. The nubmer of parameters is fixed and prescribed in the invoker's manifest file. To define a number of invoker parameters, use this in the invoker's manifest:

```json
{
    "is-invoker": true,
    "invoker-accepted-params": {
        "user": "root",
        "container": "ubuntu"
    }
}
```
 
The list of accepted parameters is defined as a json object where the order  of the items is significant:

* key of the object is just naming the invoker param (command itself to be invoked may reference it)
* values are defaults for the given invoker param (either string or null)
* order of the key value pairs describes the order of the invoker parameters.

I.e. in this particular example the invoker's arguments will be:
```
.invoke root ubuntu <invoked-binary> <invoked-binary-args>
```

Any command itself may override the invoker params using its own manifest file. For example:
```json
{
    "invoker-params": {
        "user": "john-doe",
        "container": "debian"
    }
}
```

**Important:** If some invoker params does not have a value (because default is null and command is not overriding it) then the invoker is not used and the command is invoked directly.