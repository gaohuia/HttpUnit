# HttpUnit
An alternate of PostMan. No gui, but pretty easy to send http requests in sublime. We can find the [Documentation](https://github.com/gaohuia/HttpUnit/wiki) here.

### Install

The simplest way to install HttpUnit would be through the `Package Control`. 

1. Press `Command+Shift+P`, type int "Install Package" and press `Enter`.
2. Type in "Http Unit" and press `Enter`.
3. That is all you need to do. Enjoy!

*Alternative Way*:

Press Ctrl+`
Copy the following code, parst and press enter.

```python
import urllib.request,os,hashlib,tempfile,zipfile,shutil; version = "1.1.3"; name = "HttpUnit"; url = "https://github.com/gaohuia/HttpUnit/archive/v%s.zip" % (version); pp = sublime.packages_path(); urllib.request.install_opener( urllib.request.build_opener( urllib.request.ProxyHandler()) ); by = urllib.request.urlopen(url).read(); io = tempfile.TemporaryFile(); io.write(by); temp_dir = tempfile.gettempdir(); z = zipfile.ZipFile(io); z.extractall(temp_dir); shutil.copytree(temp_dir + "/" + name + "-" + version, pp + "/" + name); io.close();
```

### Send Simple Request

Create a file named with "playground.req". Your can type some instructions in it, like:

```
// lines begin with double slashes will be ignored
// post-example

// request line:
POST http://test.com/show_post.php

// [optional] query strings
act=login
controller=user

// [optional] options
@timeout=1000
@header_in=1
@header_out=0

// [optional] http headers
Token: hello
Cookie: sessionid=anysessionid

// [optional] body
--
// Simple Kv
username: gaohuias
password: 123456
// File upload
image: @/Users/tom/images/2114647.jpeg
--
```

We can also post some raw data

```
// post-raw

// Request Line:
POST http://test.com/show_post.php

// indicts the content type, it's optional
Content-Type: application/json

// [optional] body
--raw
{
	"username" : "gaohuia"
}
--
```

### Config File

You can build a config file named `requester.json` under the project directory, and put any options in it,  which will be applied to all requests under the project automatically.

Example:

```json
{
	"header_in" : 0,
	"header_out" : 0
}
```


### Valid options

* `@timeout` The maximum number of milliseconds to allow cURL functions to execute. Default: unlimited
* `@header_in` 0/1 to control the output of the response header. Default: 1.
* `@header_out` 0/1 to control the output of the request header. Default: 1.
* `@auth` Auth method to use, valid values: basic, digest.
* `@userpwd` User && Pass to use, in format of "user:pass".


### Dependencies

* PHP 7.0+

### HOT KEYS

Just press `Command+B` (For Mac) or `Ctrl+B` (For Win) to run your script.
