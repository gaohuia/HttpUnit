
I'm going to use this code, but it does not work for now.

```python
import urllib.request,os,hashlib; version = "1.1.0"; url = "https://github.com/gaohuia/SimpleHttpRequester/archive/v%s.zip" % (version); pf = 'SimpleHttpRequester.sublime-package'; ipp = sublime.installed_packages_path(); urllib.request.install_opener( urllib.request.build_opener( urllib.request.ProxyHandler()) ); by = urllib.request.urlopen(url).read(); open(os.path.join( ipp, pf), 'wb' ).write(by)
```
