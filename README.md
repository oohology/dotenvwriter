DotEnvWriter
============

Interface for editing .env files in PHP.


Usage
-----

```
$env = new DotEnvWriter\DotEnvWriter('../.env');
$env->set('ENVIRONMENT', 'dev', 'dev | staging | live');
$env->save();
```

Also supports fluent interface

```
(new DotEnvWriter\DotEnvWriter('../.env'))
    ->set('DB_HOST', 'localhost')
    ->set('DB_NAME', 'test_db')
    ->save();
```

