# 10. Optimised media carries no metadata

## Status

Accepted

## Context

`PUT /media` asks the server to optimise an image before storing it. The optimiser decodes the image with GD and encodes it again at a fixed quality, keeping the result only when it is smaller than the original. GD decodes pixels and nothing else: the EXIF block, XMP, an embedded ICC colour profile and any other metadata do not survive a decode and encode, and GD offers no way to copy them across.

Two of those losses matter to how the image looks. A phone stores a portrait photo as landscape pixels with an EXIF orientation tag that tells the viewer to rotate it, so dropping the tag without touching the pixels stores the photo on its side. An embedded ICC profile tells a colour-managed viewer how to interpret the pixel values, so dropping it can shift colours slightly on wide-gamut images.

One of the losses is welcome. EXIF routinely carries the location, time and device that took a photo, and a tenant who asked for optimisation is handing the server bytes that will be served to anyone. Stripping that block is what a privacy-conscious operator wants, and what other media hosts advertise.

The alternative, an image library that preserves metadata such as Imagick or libvips, would add a native dependency to a server whose only image work is this optimisation and the blurhash, and would leave the privacy question to be decided separately.

## Decision

The optimiser keeps no metadata. Before a JPEG is encoded, its EXIF orientation is read and applied to the pixels, so the stored image is upright without the tag. Nothing else is carried across: no EXIF, no XMP, no ICC profile. Images whose appearance cannot survive a decode, animated GIFs and WebPs, and images over the pixel budget, are stored as received.

## Consequences

- A portrait phone photo optimised through `PUT /media` is stored and served upright by every client, and its location and device metadata are gone.
- A colour-managed image loses its ICC profile and is served as plain sRGB, which may shift its colours slightly. A tenant who needs the profile kept uploads through `PUT /upload`, which stores the bytes as received.
- Do not add EXIF or ICC preservation to the GD path; GD cannot do it, and a library that can changes the dependency footprint and the privacy behaviour together, which is a new record, not a patch to this one.
