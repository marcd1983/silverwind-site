<%-- Testimonials: one quote at a time (swSwiper) or a grid. Quote cards: Includes/TestimonyCard --%>
<% if $RenderedTitle || $Intro %>
    <div class="<% if $IsFullBleed %>sw-container<% end_if %><% if $IsCarousel %> text-center<% end_if %>">
        $RenderedTitle
        <% if $Intro %><div class="prose mb-6 max-w-none<% if $IsCarousel %> mx-auto<% end_if %>">$Intro</div><% end_if %>
    </div>
<% end_if %>
<% if $TestimonyList %>
    <% if $IsCarousel %>
        <div x-data="swSwiper" data-options="$SwiperOptionsJSON.ATT" class="relative mx-auto max-w-3xl px-1" role="region" aria-roledescription="carousel" aria-label="<% if $Title %>$Title.ATT<% else %><%t Antlion\\SilverwindSite\\Elements\\ElementTestimonials.Testimonials 'Testimonials' %><% end_if %>">
            <div class="swiper pb-2" x-ref="swiper">
                <div class="swiper-wrapper">
                    <% loop $TestimonyList %>
                        <div class="swiper-slide h-auto!" role="group" aria-roledescription="slide" aria-label="$Pos / $TotalItems">
                            <% include Antlion/SilverwindSite/Includes/TestimonyCard Large=1 %>
                        </div>
                    <% end_loop %>
                </div>
            </div>
            <% include Includes/SilverwindBlocks/SwiperControls Count=$TestimonyList.Count %>
        </div>
    <% else %>
        <ul class="$GridClass<% if $IsFullBleed %> sw-container<% end_if %>" role="list">
            <% loop $TestimonyList %>
                <li><% include Antlion/SilverwindSite/Includes/TestimonyCard %></li>
            <% end_loop %>
        </ul>
    <% end_if %>
<% end_if %>
