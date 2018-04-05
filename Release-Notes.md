This is a minor release, which includes new features and bugfixes.

To complement some necessary changes added in `1.7.0` to improve the safety of Parsedown, some new features have been added to help extensions take better advantage of the AST.

## Summary of Changes

## Added

The following have been added:
#### #576: Produce AST prior to render

#### #511: Allow element to have no name

#### #569: Add rawHtml option for extensions to use on trusted input

#### #589: Add strict mode to opt-in to strict CommonMark compliance

#### #598: Add literalBreaks support

## Fixed
The following have been resolved:

#### #566: Prevents Email autolink being started by HTML tags
Resolves: #565

#### #514: Fixes treatment of HTML blocks
By @Daniel-KM and @aidantwoods, resolves: #488, #567, #528, #259, #407, #405, #371, #370, #366, #356

#### #578: Fixes an issue where setext heading would not render if trailing spaces were present on the underline
Resolves: #571

#### #439: Improve CommonMark mixed-marker list compliance
By @PhrozenByte, resolves: #437, #581

#### #574: Remove legacy escaping
Resolves: #573, #287

#### #583: Fix trimming of internal #'s in ATX headers
Resolves: #337, #362

#### #584: Permit 1 column tables with less delimiters and assert header count matches delimiters in delimiter row
Resolves: #415

#### #586: Fix merging of adjacent blockquotes
Resolves: #344

#### #587: Fix ordered list interrupt
Resolves: #454

#### #592: Fix HTML comment endings
Resolves: #288 

#### 772c919: Recognise empty ATX headings
Resolves: #595 

#### #600: Fix fenced code block closer length rules
Resolves: #599 

## Public API Feature Notes
### Strict Mode
#589 adds a new setter to opt-in to strict CommonMark compliance.

In #265 changes were made in favour of CommonMark compliance which introduced breaking changes to previously supported syntax. Strict mode aims to side step this dichotomy between supporting a commonly used deviation from the spec, and honouring the spec.

Currently requiring a space after a hash to form an ATX header is the only case that strict mode controls, but this opens up the possibility of releasing very breaking (but compliant) changes in the future into strict mode (similar to this case) since the intent of the setter is to be compliant.

We will aim to be as compliant as possible even when strict mode is off, but if there are changes where compliance generates major unforeseen breakage in existing texts (like #265) then it is nice to be able to limit that change to those that want strict compliance.

### Literal Breaks
#598 adds a new setter which enables opt-in conversion of all `\n` to `<br />` (this is a fairly commonly requested feature: #597, #450, #448) and is not addressed by `setBreaksEnabled` if more than one new line occurs in sequence.

## Extension Accessible Feature Notes

### Text-element Adjacency
#511 adds the ability to omit (or set to `null`) the name of an element to have it render its contents only. This permits plaintext to sit adjacent to elements within the AST structure.

### Conditionally Escaped Raw HTML
#569 adds a new element content key: `rawHtml`. This can be used in place of `text`, if both keys are present `text` will be preferred. `rawHtml` can be used to write HTML directly into the AST, **however** it is strongly recommended that this is only done when absolutely necessary.

> `rawHtml` blinds Parsedown to (and thus breaks guarantees of) the output structure. You the author are therefore responsible for ensuring that HTML does not become malformed, **and also for ensuring the safe encoding (and sanitisation if also necessary) of any user-input**.

By default, `rawHtml` will be HTML encoded (in the same way as `text`) if safe-mode is enabled by the user. This facilitates conditional escaping of features which may be very powerful (like allowing arbitrary HTML), but also provides an automatic mechanism for disabling these features.
If you are confident that appropriate safety measures have been applied (i.e. contextual escaping, and sanitisation of user-input) then you can mark the `rawHtml` as trusted by setting the `allowRawHtmlInSafeMode` key to `true`.

### AST Traversal
From #576, handlers have now been decoupled from playing a necessary role in the recursion process for rendering an element. In addition to the `text` in an element, there are two new keys: `elements`, and `element`. Only one of `elements`, `element`, `text`, and `rawHtml` should be used as the content of an element (i.e. only specify one of these keys). Their tentative order of precedence is as written – specifying multiple is an error, and exceptions may be thrown in future.

In `text` Parsedown will now only expect to find a string containing text to render inside the element. In `elements`, Parsedown will expect to find an array of sub-elements to be rendered inside the element. In `element` Parsedown will expect to find a single sub-element to be rendered inside the element.
 

### More Expressive Handlers
Also from #576, previously a block using a handler in Parsedown might look something like:
```php
$Block['li'] = array(
    'name' => 'li',
    'handler' => 'li',
    'text' => !empty($matches[3]) ? array($matches[3]) : array(),
);
```

Now, this should be implemented as follows:

```php
$Block['li'] = array(
    'name' => 'li',
    'handler' => array(
        'function' => 'li',
        'argument' => !empty($matches[3]) ? array($matches[3]) : array(),
        'destination' => 'elements'
    )
);
```

Handler is now an array that contains information only relevant to handler delegation, in which the following keys should all be specified: `function`, `argument`, `destination`. `function` is the function which should be called during the handling process, `argument` is the argument that should be given to the function, `destination` is the key to which the result of this call should be written to within the element.

This serves to disambiguate the handling process, in particular the use of the `text` key to contain: text, elements, an element, and lines in an `li` isn't very intuitive and can be a source of hard to find bugs as a result.

For those wondering whether the `handler` key may be written to as a destination – yes, handlers may be defined recursively.

For any handlers still using the old string syntax – these will continue to to work via the compatibility layer summarised in https://github.com/erusev/parsedown/pull/576#issuecomment-375015648. Note that if a user enables safe-mode then this will cause the output of the handler to be HTML encoded. To avoid this either update to the new method of writing handlers, or **if the output from your handler is safe when user-input is not trusted** then you may mark the output as safe by permitting raw HTML in safe mode for the elements which use this handler.
