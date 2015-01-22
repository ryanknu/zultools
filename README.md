# zultools
Useful reusable tools in PHP

`Curl` - Added, partially tested

This class makes it really easy to use Curl by providing a significantly less archaic interface. 

`XMLFileChunker` - Not yet added or tested

This class makes it possible to read massively large XML files that cannot be serialized into a SimpleXMLElement or having to use the XMLReader. It provides a simple interface for reading lists in "chunks", and yields SimpleXMLElements one at a time and in order.

`PutData` - Not yet added or tested

Provides a simple interface for reading data from PUT without having to know how to get it. Also provides a static method for simply copying PUT data into the `$_POST` superglobal array.

`Encrypt` - Not yet added or tested

Provides some simple encryption/decryption methods that I did not write.

`Canonicalizer` - Not yet added or tested

Normalizes strings for better comparison.

`ArrayUtil` - Not yet added or tested

Provides some helpful array methods.

`Code` - Not yet added or tested

Provides the ability to make "codes" or random strings.

`HMACSHA1` - Not yet added or tested

Provides the HMACSHA1 signing algorithm for use with Amazon S3.

`Phone` - Not yet added or tested

Provides methods for working with phone numbers.

`UUID` - Not yet added or tested

Creates GUID's
