
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Progress } from "@/components/ui/progress";
import { useToast } from "@/hooks/use-toast";

const Index = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [albums, setAlbums] = useState([]);
  const { toast } = useToast();
  
  // Mock function to simulate loading albums
  const handleLoadAlbums = () => {
    setIsLoading(true);
    // Simulate API call
    setTimeout(() => {
      setAlbums([
        {
          id: 1,
          title: "Summer Vacation",
          photoCount: 42,
          coverImage: "/placeholder.svg",
          date: "2024-06-15"
        },
        {
          id: 2,
          title: "Family Gathering",
          photoCount: 78,
          coverImage: "/placeholder.svg",
          date: "2024-05-22"
        },
        {
          id: 3,
          title: "Nature Photography",
          photoCount: 53,
          coverImage: "/placeholder.svg",
          date: "2024-04-10"
        }
      ]);
      setIsLoading(false);
      toast({
        title: "Albums Loaded",
        description: "Successfully loaded 3 albums",
      });
    }, 1500);
  };

  return (
    <div className="min-h-screen flex flex-col bg-gray-100 p-4">
      <div className="max-w-5xl w-full mx-auto bg-white rounded-lg shadow-lg p-6 mb-6">
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
          <p className="mb-4">
            To use this plugin in a real WordPress site, download the plugin files and upload them
            to your WordPress plugins directory, then activate the plugin from the WordPress admin panel.
          </p>
        </div>
      </div>
      
      {/* Demo Album Section */}
      <div className="max-w-5xl w-full mx-auto bg-white rounded-lg shadow-lg p-6">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-bold">Albums Demo</h2>
          <Button 
            onClick={handleLoadAlbums} 
            disabled={isLoading}
          >
            {isLoading ? "Loading..." : "Load Albums"}
          </Button>
        </div>
        
        {isLoading ? (
          <div className="space-y-4">
            <p>Loading albums...</p>
            <Progress value={45} className="h-2" />
            <div className="grid md:grid-cols-3 gap-4">
              {[1, 2, 3].map((i) => (
                <div key={i} className="border rounded-md p-4">
                  <Skeleton className="h-[200px] w-full mb-3" />
                  <Skeleton className="h-6 w-3/4 mb-2" />
                  <Skeleton className="h-5 w-1/2" />
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="grid md:grid-cols-3 gap-4">
            {albums.length > 0 ? (
              albums.map(album => (
                <div key={album.id} className="border rounded-md overflow-hidden">
                  <div className="h-[200px] bg-gray-100 relative">
                    <img 
                      src={album.coverImage} 
                      alt={album.title} 
                      className="w-full h-full object-cover"
                    />
                  </div>
                  <div className="p-4">
                    <h3 className="text-lg font-semibold">{album.title}</h3>
                    <div className="text-sm text-gray-600 flex justify-between mt-1">
                      <span>{album.photoCount} photos</span>
                      <span>{album.date}</span>
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div className="col-span-3 py-8 text-center">
                <p className="text-gray-500">No albums loaded. Click the "Load Albums" button to see a demo.</p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default Index;
