{{-- Old WordPress links like /?p=123 load the homepage; send them to the right place. --}}
@include('_partials.redirect-data')
<script>
(function () {
    var q = new URLSearchParams(location.search), to = null, v;
    if ((v = q.get('p')) && SSR.ids[v]) to = SSR.ids[v];
    else if (q.has('feed')) to = SSR.base + '/feed.xml';
    else if ((v = q.get('m')) && /^\d{4}(\d{2})?/.test(v)) to = SSR.base + '/' + v.slice(0, 4) + '/' + (v.length >= 6 ? v.slice(4, 6) + '/' : '');
    else if ((v = q.get('tag'))) to = SSR.base + '/tag/' + encodeURIComponent(v) + '/';
    else if ((v = q.get('category_name'))) to = SSR.base + '/category/' + encodeURIComponent(v) + '/';
    else if ((v = q.get('author')) && SSR.authors[v]) to = SSR.base + '/author/' + SSR.authors[v] + '/';
    else if ((v = q.get('author_name'))) to = SSR.base + '/author/' + encodeURIComponent(v) + '/';
    if (to) location.replace(to);
})();
</script>
