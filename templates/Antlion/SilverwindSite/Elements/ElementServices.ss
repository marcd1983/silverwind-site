<%-- Services block: a grid or a swSwiper carousel of ServiceCards. Full width: the heading lines up with the site; carousel cards run to the screen edges --%>
<% if $RenderedTitle || $Intro %>
    <div class="<% if $IsFullBleed %>sw-container<% end_if %>">
        $RenderedTitle
        <% if $Intro %><div class="prose mb-6 max-w-none">$Intro</div><% end_if %>
    </div>
<% end_if %>
<% if $ServiceList %>
    <% if $IsCarousel %>
        <div x-data="swSwiper" data-options="$SwiperOptionsJSON.ATT" class="relative<% if not $IsFullBleed %> px-1<% end_if %>" role="region" aria-roledescription="carousel" aria-label="<% if $Title %>$Title.ATT<% else %><%t Antlion\\SilverwindSite\\Elements\\ElementServices.Services 'Services' %><% end_if %>">
            <div class="swiper py-2<% if $IsFullBleed %> px-gutter<% end_if %>" x-ref="swiper">
                <div class="swiper-wrapper">
                    <% loop $ServiceList %>
                        <div class="swiper-slide h-auto!" role="group" aria-roledescription="slide" aria-label="$Pos / $TotalItems">
                            <% include Antlion/SilverwindSite/Includes/ServiceCard Heading=$Up.CardHeading %>
                        </div>
                    <% end_loop %>
                </div>
            </div>
            <% include Includes/SilverwindBlocks/SwiperControls Count=$ServiceList.Count, ArrowsInside=$IsFullBleed %>
        </div>
    <% else %>
        <ul class="$GridClass<% if $IsFullBleed %> sw-container<% end_if %>" role="list">
            <% loop $ServiceList %>
                <li><% include Antlion/SilverwindSite/Includes/ServiceCard Heading=$Up.CardHeading %></li>
            <% end_loop %>
        </ul>
    <% end_if %>
<% end_if %>
