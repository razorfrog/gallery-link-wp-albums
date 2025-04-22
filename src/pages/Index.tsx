
const Index = () => {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100 p-4">
      <div className="max-w-3xl w-full bg-white rounded-lg shadow-lg p-6">
        <h1 className="text-3xl font-bold text-center mb-6">WP Gallery Link</h1>
        <p className="text-lg text-center mb-8">
          A WordPress plugin that connects with Google Photos to create a custom post type for albums.
        </p>
        
        <div className="grid md:grid-cols-2 gap-6">
          <div className="bg-gray-50 p-4 rounded-lg">
            <h2 className="text-xl font-semibold mb-3">Key Features</h2>
            <ul className="list-disc pl-5 space-y-2">
              <li>Google Photos integration</li>
              <li>Custom post type for albums</li>
              <li>Import album titles, images, and dates</li>
              <li>Category organization</li>
              <li>Customizable display options</li>
              <li>Responsive gallery layout</li>
            </ul>
          </div>
          
          <div className="bg-gray-50 p-4 rounded-lg">
            <h2 className="text-xl font-semibold mb-3">Plugin Usage</h2>
            <p className="mb-3">
              After installing the plugin in WordPress:
            </p>
            <ol className="list-decimal pl-5 space-y-2">
              <li>Connect to Google Photos API</li>
              <li>Import albums from your account</li>
              <li>Organize with categories</li>
              <li>Display with shortcode: <code>[wp_gallery_link]</code></li>
            </ol>
          </div>
        </div>
        
        <div className="mt-8 p-4 bg-blue-50 rounded-lg">
          <h2 className="text-xl font-semibold mb-3">Installation</h2>
          <p>
            To use this plugin in a real WordPress site, download the plugin files and upload them
            to your WordPress plugins directory, then activate the plugin from the WordPress admin panel.
          </p>
        </div>
      </div>
    </div>
  );
};

export default Index;
