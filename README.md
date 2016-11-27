# TinyList

TinyList is a hobby project, aiming to provide a simple and colourful way to make lists (for whatever purpose) through a web-interface.

The lists are stored as text files, allowing users to edit them freely using their own text editor if they have access to the location they are stored. Within a list, items can be grouped using the grouping delimiter, currently ':'. For instance, in a list "Good reads", the item "Ian Fleming: Casino Royale" will be shown as follows (with additional formatting, naturally):

* Ian Fleming
  * Casino Royale

As lists are stored as text files, no database configuration is needed. The code for TinyList can be pasted and runs anywhere in an active web directory --- for instance, *localhost/tinylist*.

Currently, lists are constructed as follows:

```
Name ListName
Colour ListColour (optional)

BeginItems
Item ...
Item ...
EndItems
```
