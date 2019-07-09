# sublime-requester
An alternative of PostMan. No gui, but pretty easy to send http requests in sublime.

<img src="https://raw.githubusercontent.com/gaohuia/sublime-requester/master/gifs/post.gif" />

### Sending simple request

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


### Valid options

* `@timeout` The maximum number of seconds to allow cURL functions to execute.
* `@header_in` 0/1 to control the output of the response header.
* `@header_out` 0/1 to control the output of the request header.


### Dependencies

* PHP 7.0+

### HOT KEYS

Just press `Command+B` (For Mac) or `Ctrl+B` (For Win) to run your script.
