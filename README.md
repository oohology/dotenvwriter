DotEnvWriter
============
Interface for editing .env files in PHP

[![Build Status](https://travis-ci.org/oohology/dotenvwriter.svg?branch=master)](https://travis-ci.org/oohology/dotenvwriter)

----------

Note: This is probably not advisable for use in production, but could be handy for automating installation tasks, etc.

Basic Usage
-----
In general, you will open a .env file, use the `set` method to append or replace some
values, and then call the `save` method to write the result.

Open an environment file and replace a value:
```
use DotEnvWriter\DotEnvWriter;

$env = new DotEnvWriter('../.env');
$env->set('ENVIRONMENT', 'dev');
$env->save();
```

Also supports fluent interface:
```
(new DotEnvWriter('../.env'))
    ->set('DB_HOST', 'localhost')
    ->set('DB_NAME', 'test_db')
    ->save();
```

Output File
---------------
There are multiple ways to read from a source file, make some changes, and write the result to a different output file.  The end result is the same, so choose whichever method best fits your use case:
```
$writer = (new DotEnvWriter('.env.example'))
	->setOutputPath('.env');
// ...
$writer->save();
```
Or alternately:
```
$writer = (new DotEnvWriter('.env.example'));
// ...
$writer->save('.env');
```
Or using the `load()` method:
```
$writer = (new DotEnvWriter('.env'))
	->load('.env.example');
// ...
$writer->save();
```

Comments
-------------
The `set()` method takes a `$comments` parameter. If omitted (or set to `null`),  any existing comment from the source file with be kept intact. If a `$comment` is provided it will overwrite the existing comment. Providing a zero-length 	`$comment` will cause the comment to be deleted.

**Examples:**
Set a comment
```
// source: API_KEY=""
$writer->set('API_KEY', '1234', 'four-digit code');
// result: API_KEY=1234 # four-digit code
```
Delete a comment
```
// source: API_KEY= # four-digit code
$writer->set('API_KEY', '1234', '');
// result: API_KEY=1234
```
Keep existing comment
```
// source: API_KEY= # four-digit code
$writer->set('API_KEY', '1234');
// result: API_KEY=1234 # four-digit code
```

Export
---------
The parser supports a bash-style `export` prefix on any line. The `set()` method
takes an `$export` variable as its 4th argument.
**Example:**
By default, keep the existing state
```
// source: export API_KEY=""
$writer->set('API_KEY', '1234');
// result: export API_KEY=1234
```
Or change it by passing a boolean
```
// source: export API_KEY=""
$writer->set('API_KEY', '1234', null, false);
// result: API_KEY=1234
$writer->set('API_KEY', '1234', null, true);
// result: export API_KEY=1234
```

Casting booleans
---------
By default boolean values will not be stored as `true` or `false`. 

To enable casting booleans you should call the `castBooleans()` method.

**Example:**

Default behaviour
```
$writer->set('REGISTRATION_OPENED', true);
// result: REGISTRATION_OPENED=1


$writer->set('REGISTRATION_OPENED', false);
// result: REGISTRATION_OPENED=
```

After calling `castBooleans()` method

```
$writer->castBooleans();

$writer->set('REGISTRATION_OPENED', true);
// result: REGISTRATION_OPENED=true


$writer->set('REGISTRATION_OPENED', false);
// result: REGISTRATION_OPENED=false
```

Read the Value of a Variable
--------------------
The `get` method allows you to find the value of an existing given environment
variable. It returns false if the variable doesn't exist, or an array containing
the details of the line from the source file.

Source:
`export ENV="dev" # dev or live?`

Get command:
`$writer->get('ENV');`

Result:
```
[
    'line' => 'export ENV="dev" # dev or live?',
    'export' => 'export',
    'key' => 'ENV',
    'value' => 'dev',
    'comment' => 'dev or live?'
];
```

Writing Blank/Comment Lines
----------------------
The `line` method appends a single unprocessed line of output to the file. It could be used
to insert blank lines or comments. If you wish to append a new variable, the
`set` method should be used instead to prevent duplicates.

```
$writer = (new DotEnvWriter)
    ->line()
    ->line('# App Settings')
    ->line()
    ->set('APP_ENV', 'dev');
```
