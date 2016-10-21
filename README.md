DotEnvWriter
============

Interface for editing .env files in PHP.

This is probably not advisable for use in production, but could be handy for
automating installation tasks, etc.



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

