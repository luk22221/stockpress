# Test fixtures

- `photo-with-exif.jpg` – 600×500 JPEG built for the tests: an EXIF block
  (Make, Model, Orientation, DateTimeOriginal and a binary MakerNote that must
  be filtered out) plus IPTC data with Polish characters (caption 2#120).

Other images used by the tests are generated on the fly with Imagick.
