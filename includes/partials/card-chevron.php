<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The double-chevron mark on a member card.
 *
 * Traced from Figma node I414:803;224:2337 / ;224:2339 ("Arrow2 1
 * [Vectorized]") as the redesigned card instantiates it: a 29x24.591 box holding
 * exactly TWO vector children, both filled solid #6A4C91.
 *
 * This replaced a 171x145, four-child version. That one drew each chevron as a
 * purple outline around an inner shape filled in the card's own #D2D8EE, so it
 * read as hollow. The redesign draws both chevrons solid, which is why the
 * knockout fill (`--hh-chevron-inner`) is gone rather than merely retuned --
 * with the card background now 38% lavender there is no longer a single opaque
 * colour a knockout could be filled with anyway.
 *
 * The child offsets below are that node's own percentage insets resolved against
 * the 29x24.591 box (left chevron at 7.37%/8.54%, right at 50.56%/8.55%), and
 * each path's own coordinate space is already its natural ~12.2x20.5, so the
 * offsets are plain translations with no scaling. The 180-degree rotation is the
 * one the card applies to the instance, so the chevrons point right as they do
 * in the design.
 *
 * Still inlined rather than referenced as a file because the fill is a CSS
 * custom property: on hover the card background turns purple and the mark has to
 * follow it to white. An <img> could not do that.
 *
 * @return string
 */
function honest_team_render_card_chevron() {
	return '<svg class="honest-member-card__chevron" viewBox="0 0 29 24.591" width="29" height="24.591" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
  <g transform="rotate(180 14.5 12.2955)">
    <path transform="translate(14.662 2.103)" fill="var(--hh-chevron, #6a4c91)" d="M10.3072 0C10.9334 0.585427 11.5206 1.23585 12.1676 1.82933L3.73617 10.2477C4.33457 10.7459 5.26524 11.7241 5.85558 12.3069L9.91927 16.3314C10.6303 17.0379 11.4787 17.9324 12.2041 18.5787C11.563 19.1974 10.9322 19.8268 10.3119 20.4666C9.57611 19.6626 8.61291 18.7447 7.82877 17.961L3.2745 13.4253L1.16839 11.3399C0.861774 11.0373 0.328963 10.4651 0 10.2462C3.3891 6.78651 6.91336 3.4435 10.3072 0Z"/>
    <path transform="translate(2.137 2.100)" fill="var(--hh-chevron, #6a4c91)" d="M10.2317 0C10.3683 0.0385395 11.881 1.59377 12.1374 1.82335L6.53755 7.42196C5.60641 8.35158 4.62101 9.29916 3.71955 10.2488C4.42742 10.8926 5.22428 11.7251 5.90786 12.4068L9.75015 16.2385C10.3362 16.8224 11.5886 18.1342 12.1831 18.5798C11.7397 18.9256 10.7096 19.9983 10.2875 20.4235C10.2228 20.407 8.77665 18.9398 8.55814 18.7216L2.67487 12.8913L0.92889 11.1653C0.681203 10.922 0.200838 10.4782 0 10.2367C0.766846 9.39384 1.86749 8.35086 2.69285 7.52533L7.88266 2.34646C8.59807 1.63434 9.57678 0.724703 10.2317 0Z"/>
  </g>
</svg>';
}
