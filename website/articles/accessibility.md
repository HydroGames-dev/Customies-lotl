# Reading controls and platform support

The wiki uses system-font fallbacks so text can render without a third-party font
download. It is designed for current desktop and mobile browsers; actual devices,
browser versions, accessibility settings and client hardware still vary.

## Adjust the page

- Use the theme button to switch light/dark appearance. The initial choice follows
  your system preference; your explicit choice is remembered locally.
- Use **Aa** to cycle reading size through 100%, 110%, 120% and 90%. Browser zoom
  remains available and is not disabled by the page.
- On a narrow screen, open **Menu** for topic folders and the section switch.
- Use **Ctrl K** or **Command K** to search the current documentation section.
- A progress line tracks reading position; the back-to-top button appears on longer pages.

## Motion and keyboard use

Hover and page transitions are brief and decorative. The page honors the operating
system's reduced-motion preference; smooth scrolling, hover movement and visual
transitions are removed in that mode. Demo flipbooks also respect reduced motion
and can be paused in their own gallery section.

Use Tab to reach links and controls. Focus has a visible outline, the skip link
jumps to the article, folders use native disclosures and form fields have labels.
Tool outputs and errors announce a short status; longer JSON/code can be read in
the result panel. Code blocks and wide tables scroll within the article rather
than requiring the entire page to scroll sideways.

## JavaScript and local preferences

Documentation is built as static HTML. JavaScript enables search, theme controls,
calculators, generated-file downloads and geometry previews. Tool submit buttons
remain disabled if their scripts cannot load. A blocked local-storage setting
does not prevent reading or using the page; it only prevents saving preferences.

Tool inputs are processed in the current browser and are not uploaded. Reloading
can discard tool inputs/results. These conveniences do not replace the PHP/host
checks and real-client tests described in the guides.
