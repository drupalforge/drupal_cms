/* ============================================================
   San Francisco Drupal Users Group — homepage behavior
   ------------------------------------------------------------
   Edit the two arrays below to update the video listings.
   Each month, add the new talk to the top of `featured`.
   ============================================================ */

/* The 2026 season — videos shown with their real YouTube promo
   thumbnails. `videoId` is the YouTube ID; the thumbnail and watch
   link are built from it automatically. */
var featured = [
  {
    label: 'January 2026',
    title: 'New Revenue Streams with Drupal: SMB, SaaS, Marketplace, Drupito & more',
    speaker: 'Ashraf Abed',
    videoId: 'i4iIHlpgdIE'
  },
  {
    label: 'February 2026',
    title: 'What\u2019s Next for Drupal AI? A Look at the 2026 Roadmap',
    speaker: 'Kristen Pol',
    videoId: 'hrvvWWAUps4'
  },
  {
    label: 'March 2026',
    title: 'Modern Drupal Theming with Dripyard',
    speaker: 'Mike Herchel',
    videoId: 'ZKseCW9VoYw'
  },
  {
    label: 'April 2026',
    title: 'Drupal Canvas Has Landed: Site Building in Drupal CMS 2.0',
    speaker: 'Matt Glaman',
    videoId: 't4_avk-i1RA'
  }
];

/* The playlist these belong to (used to build watch links). */
var PLAYLIST = 'PLmPB9mMsHZp3SsU16ajK34oxniNOdu255';

/* Earlier favorites — gradient placeholder thumbnails. Drop in a
   `videoId` and switch these to the featured style whenever you want
   to surface the real promo art. */
var archive = [
  { title: 'Viewsapalooza: Favorite Views Tools & Tricks', speaker: 'SFDUG community',      date: 'March 9, 2023', url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#3a2c7e,#6c5cc4)' },
  { title: 'Optimize Your Images to Speed Up Your Site',    speaker: 'Martin Anderson-Clutz', date: 'Sept 8, 2022',  url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#244a63,#3f7d96)' },
  { title: 'An Update on WCAG 3.0',                         speaker: 'Jaunita George',        date: 'Nov 10, 2022',  url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#7a3f2a,#c47a4a)' },
  { title: 'Drupal Views by Example',                       speaker: 'Mauricio Dinarte',      date: 'July 14, 2022', url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#2c2160,#4a3a9e)' },
  { title: 'Tighten Up Your Drupal Code with PHPStan',      speaker: 'Matt Glaman',           date: 'March 10, 2022', url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#1f4d2e,#3e6b45)' },
  { title: 'ECA: A New Rules Engine for Drupal 9+',         speaker: 'J\u00fcrgen Haas',       date: 'Jan 14, 2022',  url: 'https://www.youtube.com/@sfdug', bg: 'linear-gradient(135deg,#4a2c6e,#8a5cc4)' }
];

var PLAY_SVG = '<svg width="16" height="16" viewBox="0 0 18 18" fill="#fff" aria-hidden="true"><path d="M4 3l11 6-11 6z"></path></svg>';
var BADGE_SVG = '<svg class="play-badge" width="58" height="40" viewBox="0 0 58 40" aria-hidden="true"><rect width="58" height="40" rx="11" fill="#E33A2E"></rect><path d="M23 12l15 8-15 8z" fill="#fff"></path></svg>';

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function featuredCard(f) {
  var thumb = 'https://img.youtube.com/vi/' + f.videoId + '/maxresdefault.jpg';
  var url = 'https://www.youtube.com/watch?v=' + f.videoId + '&list=' + PLAYLIST;
  return '' +
    '<a class="card" href="' + url + '" target="_blank" rel="noopener">' +
      '<div class="card-thumb">' +
        '<img src="' + thumb + '" alt="' + esc(f.title) + '" loading="lazy">' +
        '<div class="fade"></div>' +
        '<span class="watch-pill">' + PLAY_SVG + ' Watch recording</span>' +
      '</div>' +
      '<div class="card-body">' +
        '<div class="card-date">' + esc(f.label) + '</div>' +
        '<div class="card-title">' + esc(f.title) + '</div>' +
        '<div class="card-speaker">' + esc(f.speaker) + '</div>' +
      '</div>' +
    '</a>';
}

function archiveCard(r) {
  return '' +
    '<a class="card card--sm" href="' + r.url + '" target="_blank" rel="noopener">' +
      '<div class="card-thumb" style="background:' + r.bg + '">' +
        '<div class="thumb-fade-archive"></div>' +
        '<span class="thumb-tag">SFDUG</span>' +
        BADGE_SVG +
      '</div>' +
      '<div class="card-body">' +
        '<div class="card-date">' + esc(r.date) + '</div>' +
        '<div class="card-title">' + esc(r.title) + '</div>' +
        '<div class="card-speaker">' + esc(r.speaker) + '</div>' +
      '</div>' +
    '</a>';
}

document.addEventListener('DOMContentLoaded', function () {
  var fg = document.getElementById('featured-grid');
  var ag = document.getElementById('archive-grid');
  if (fg) fg.innerHTML = featured.map(featuredCard).join('');
  if (ag) ag.innerHTML = archive.map(archiveCard).join('');

  var form = document.getElementById('news-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('news-msg');
      if (msg) msg.textContent = 'Thanks! You\u2019re on the list \u2014 watch your inbox before the next meetup.';
      form.reset();
    });
  }
});
