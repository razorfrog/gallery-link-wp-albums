
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Progress } from "@/components/ui/progress";
import { useToast } from "@/hooks/use-toast";
import { AlbumCard } from "@/components/AlbumCard";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";
import { InfoIcon, RefreshCw } from "lucide-react";

type Album = {
  id: number;
  title: string;
  photoCount: number;
  coverImage: string;
  date: string;
};

type LoadingLog = {
  id: number;
  message: string;
  timestamp: string;
};

const Index = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [albums, setAlbums] = useState<Album[]>([]);
  const [progress, setProgress] = useState(0);
  const [logs, setLogs] = useState<LoadingLog[]>([]);
  const { toast } = useToast();
  
  const addLog = (message: string) => {
    setLogs(currentLogs => [
      ...currentLogs, 
      { 
        id: Date.now(), 
        message, 
        timestamp: new Date().toLocaleTimeString() 
      }
    ]);
  };
  
  const resetState = () => {
    setIsLoading(false);
    setProgress(0);
    setLogs([]);
    setAlbums([]);
  };
  
  // Mock function to simulate loading albums
  const handleLoadAlbums = () => {
    // Reset state before starting
    resetState();
    
    setIsLoading(true);
    
    addLog("Starting to load albums...");
    
    // Simulate API call with progress updates
    let progressValue = 0;
    const progressInterval = setInterval(() => {
      progressValue += 5;
      setProgress(progressValue);
      
      if (progressValue === 25) {
        addLog("Authenticating with Google Photos API...");
      }
      
      if (progressValue === 50) {
        addLog("Fetching album list from Google...");
      }
      
      if (progressValue === 75) {
        addLog("Processing album data...");
      }
      
      if (progressValue >= 100) {
        clearInterval(progressInterval);
        setProgress(100);
        finishLoading();
      }
    }, 125);
    
    // Safety timeout to ensure loading eventually completes even if there's an error
    setTimeout(() => {
      if (isLoading && progress < 100) {
        clearInterval(progressInterval);
        setProgress(100);
        addLog("Loading timed out, but we'll show some albums anyway");
        finishLoading();
      }
    }, 5000);
  };

  const finishLoading = () => {
    addLog("Albums loaded successfully!");
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
  };

  useEffect(() => {
    // Clean up interval on component unmount
    return () => {
      resetState();
    };
  }, []);

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
          <div className="flex gap-2">
            {isLoading && (
              <Button 
                variant="outline"
                onClick={resetState}
                className="flex items-center gap-1"
              >
                <RefreshCw className="h-4 w-4" /> Reset
              </Button>
            )}
            <Button 
              onClick={handleLoadAlbums} 
              disabled={isLoading}
            >
              {isLoading ? "Loading..." : "Load Albums"}
            </Button>
          </div>
        </div>
        
        {isLoading && (
          <div className="space-y-4 mb-6">
            <div className="flex items-center justify-between mb-2">
              <p>Loading albums...</p>
              <span className="text-sm font-bold text-gray-500">{progress}%</span>
            </div>
            <Progress value={progress} className="h-2" />
            
            <Alert className="bg-blue-50 border-blue-200">
              <InfoIcon className="h-4 w-4" />
              <AlertTitle>Loading Status Log</AlertTitle>
              <AlertDescription>
                <div className="mt-2 max-h-40 overflow-y-auto border rounded-md p-2 bg-white">
                  {logs.length > 0 ? (
                    logs.map((log) => (
                      <div key={log.id} className="text-sm py-1 border-b border-gray-100 last:border-0">
                        <span className="text-gray-500 mr-2">[{log.timestamp}]</span>
                        {log.message}
                      </div>
                    ))
                  ) : (
                    <div className="text-sm py-1 text-gray-500">No logs yet...</div>
                  )}
                </div>
              </AlertDescription>
            </Alert>
            
            <div className="grid md:grid-cols-3 gap-4">
              {[1, 2, 3].map((i) => (
                <AlbumCard
                  key={i}
                  id={i}
                  title=""
                  photoCount={0}
                  coverImage=""
                  date=""
                  isLoading={true}
                />
              ))}
            </div>
          </div>
        )}
        
        {!isLoading && (
          <div className="grid md:grid-cols-3 gap-4">
            {albums.length > 0 ? (
              albums.map(album => (
                <AlbumCard
                  key={album.id}
                  id={album.id}
                  title={album.title}
                  photoCount={album.photoCount}
                  coverImage={album.coverImage}
                  date={album.date}
                  isLoading={false}
                />
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
